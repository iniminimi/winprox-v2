<?php

declare(strict_types=1);

use App\Actions\Notifications\SaveWorkerPushSubscriptionAction;
use App\Actions\Notifications\SendWorkerPushAction;
use App\Actions\Team\NotifyWorkersOfVisibleLibraryAction;
use App\Actions\Team\UpdateTeamDocumentAction;
use App\Actions\Time\DecideAbsenceRequestAction;
use App\Actions\Time\NotifyWorkersRosterChangedAction;
use App\Actions\Time\RemindForgottenClockOutsAction;
use App\Contracts\WebPushClient;
use App\Data\Notifications\WebPushResult;
use App\Data\Time\DecideAbsenceRequestData;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkerNotificationType;
use App\Models\AbsenceRequest;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\TeamAnnouncement;
use App\Models\TeamDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Models\WorkerNotification;
use App\Models\WorkShift;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Tenancy;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
    Tenancy::forget();
});

beforeEach(function () {
    config([
        'push.public_key' => 'test-public',
        'push.private_key' => 'test-private',
        'push.subject' => 'mailto:info@winprox.app',
    ]);

    $this->push = new class implements WebPushClient
    {
        /** @var list<array{endpoint: string, payload: array<string, mixed>}> */
        public array $sent = [];

        public bool $gone = false;

        public function send(string $endpoint, string $publicKey, string $authToken, string $payload): WebPushResult
        {
            $decoded = json_decode($payload, true);

            $this->sent[] = [
                'endpoint' => $endpoint,
                'payload' => is_array($decoded) ? $decoded : [],
            ];

            return $this->gone ? WebPushResult::gone() : WebPushResult::delivered();
        }
    };

    app()->instance(WebPushClient::class, $this->push);
});

/**
 * @return array{0: Tenant, 1: InternalTeam, 2: Worker, 3: WorkerDevice}
 */
function pushWorker(string $endpoint): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    $device = WorkerDevice::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'push_endpoint' => $endpoint,
        'push_endpoint_hash' => hash('sha256', $endpoint),
        'push_public_key' => 'publickey',
        'push_auth_token' => 'authtoken',
        'push_locale' => 'nl',
    ]);
    $worker->forceFill(['clock_device_id' => $device->id])->save();

    return [$tenant, $team, $worker->fresh(), $device->fresh()];
}

it('pusht een gewijzigde dienst één keer', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-17 09:00:00'));
    [$tenant, , $worker] = pushWorker('https://push.example.test/roster');

    $notify = app(NotifyWorkersRosterChangedAction::class);
    $notify->handle($tenant, [['worker_id' => $worker->id, 'date' => '2026-11-18']]);
    $notify->handle($tenant, [['worker_id' => $worker->id, 'date' => '2026-11-18']]);

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['payload']['title'])->toBe('Je dienst is gewijzigd')
        ->and($this->push->sent[0]['payload']['body'])->toContain('18-11-2026')
        ->and($this->push->sent[0]['payload']['url'])->toContain('open=schedule');
});

it('pusht een nieuw document naar het team en een persoonlijk document alleen naar die uitvoerder', function () {
    [$tenant, $team, $worker] = pushWorker('https://push.example.test/docs-a');
    $other = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    $otherDevice = WorkerDevice::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $other->id,
        'push_endpoint' => 'https://push.example.test/docs-b',
        'push_endpoint_hash' => hash('sha256', 'https://push.example.test/docs-b'),
        'push_public_key' => 'publickey',
        'push_auth_token' => 'authtoken',
        'push_locale' => 'nl',
    ]);
    $other->forceFill(['clock_device_id' => $otherDevice->id])->save();

    $shared = TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'worker_id' => null,
        'is_active' => true,
    ]);
    app(NotifyWorkersOfVisibleLibraryAction::class)->handleDocument($shared);
    app(NotifyWorkersOfVisibleLibraryAction::class)->handleDocument($shared);

    expect($this->push->sent)->toHaveCount(2);

    $personal = TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'worker_id' => $worker->id,
        'is_active' => true,
    ]);
    app(NotifyWorkersOfVisibleLibraryAction::class)->handleDocument($personal);

    expect($this->push->sent)->toHaveCount(3)
        ->and(collect($this->push->sent)->pluck('endpoint')->last())->toBe('https://push.example.test/docs-a');
});

it('pusht een document pas als het zichtbaar wordt', function () {
    [$tenant, $team, $worker] = pushWorker('https://push.example.test/activate');
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $document = TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'worker_id' => null,
        'is_active' => false,
        'description' => 'Huisreglement',
    ]);

    $update = app(UpdateTeamDocumentAction::class);
    $update->handle($document, [
        'description' => 'Huisreglement bijgewerkt',
        'worker_id' => null,
        'is_active' => false,
    ], null, $tenant->id, $admin->id);

    expect($this->push->sent)->toHaveCount(0);

    $update->handle($document->fresh(), [
        'description' => 'Huisreglement',
        'worker_id' => null,
        'is_active' => true,
    ], null, $tenant->id, $admin->id);
    $update->handle($document->fresh(), [
        'description' => 'Huisreglement nog eens',
        'worker_id' => null,
        'is_active' => true,
    ], null, $tenant->id, $admin->id);

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['payload']['title'])->toBe('Nieuw document')
        ->and(WorkerNotification::query()->where('worker_id', $worker->id)->where('type', WorkerNotificationType::TeamDocument)->count())->toBe(1);
});

it('pusht een verlopen mededeling niet', function () {
    [$tenant, $team] = pushWorker('https://push.example.test/news');
    $announcement = TeamAnnouncement::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
        'expires_at' => now()->subDay(),
    ]);

    app(NotifyWorkersOfVisibleLibraryAction::class)->handleAnnouncement($announcement);

    expect($this->push->sent)->toHaveCount(0);
});

it('pusht goedgekeurd en geweigerd verlof', function () {
    [$tenant, , $worker] = pushWorker('https://push.example.test/leave');
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $leaveType = ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'kind' => ShiftTypeKind::Leave,
        'start_time' => null,
        'end_time' => null,
        'is_active' => true,
    ]);
    $approved = AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'shift_type_id' => $leaveType->id,
        'kind' => ShiftTypeKind::Leave,
        'date_from' => '2026-11-20',
        'date_to' => '2026-11-20',
        'status' => 'pending',
    ]);
    $rejected = AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'shift_type_id' => $leaveType->id,
        'kind' => ShiftTypeKind::Leave,
        'date_from' => '2026-11-21',
        'date_to' => '2026-11-22',
        'status' => 'pending',
    ]);

    $decide = app(DecideAbsenceRequestAction::class);
    $decide->handle($tenant, $approved, new DecideAbsenceRequestData(true, ''), $admin);
    $decide->handle($tenant, $rejected, new DecideAbsenceRequestData(false, 'Bezet'), $admin);

    expect($this->push->sent)->toHaveCount(2)
        ->and($this->push->sent[0]['payload']['title'])->toBe('Verlof goedgekeurd')
        ->and($this->push->sent[0]['payload']['body'])->toContain('20-11-2026')
        ->and($this->push->sent[0]['payload']['url'])->toContain('open=absence')
        ->and($this->push->sent[1]['payload']['title'])->toBe('Verlof geweigerd')
        ->and($this->push->sent[1]['payload']['body'])->toContain('21-11-2026')
        ->and($this->push->sent[1]['payload']['url'])->toContain('open=absence');
});

it('herinnert één keer aan uitklokken na het laatste blok', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-17 12:20:00'));
    [$tenant, $team, $worker] = pushWorker('https://push.example.test/clock');
    $point = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $point->id,
        'clock_in_at' => Carbon::parse('2026-11-17 08:00:00'),
    ]);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-11-17',
        'start_time' => '08:00',
        'end_time' => '12:00',
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
    ]);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-11-17',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
    ]);

    $remind = app(RemindForgottenClockOutsAction::class);
    expect($remind->handle())->toBe(0);

    Carbon::setTestNow(Carbon::parse('2026-11-17 17:16:00'));
    expect($remind->handle())->toBe(1)
        ->and($this->push->sent[0]['payload']['title'])->toBe('Je bent nog ingeklokt');

    expect($remind->handle())->toBe(0)
        ->and($this->push->sent)->toHaveCount(1);
});

it('stuurt geen uitklokherinnering zonder gepubliceerd rooster', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-17 18:00:00'));
    [$tenant, $team, $worker] = pushWorker('https://push.example.test/noroster');
    $point = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $point->id,
        'clock_in_at' => Carbon::parse('2026-11-17 08:00:00'),
    ]);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-11-17',
        'start_time' => '08:00',
        'end_time' => '12:00',
        'status' => PlannedShiftStatus::Draft,
        'kind' => ShiftTypeKind::Work,
    ]);

    expect(app(RemindForgottenClockOutsAction::class)->handle())->toBe(0)
        ->and($this->push->sent)->toHaveCount(0);
});

it('wist een verlopen push-abonnement', function () {
    [, , $worker, $device] = pushWorker('https://push.example.test/gone');
    $this->push->gone = true;

    $result = app(SendWorkerPushAction::class)->handle(
        (int) $worker->tenant_id,
        (int) $worker->id,
        'time.portal.push.clock_out_title',
        'time.portal.push.clock_out_body',
        [],
        'https://winprox.test/time-portal/1',
        'clock_out_reminder:1',
    );

    expect($result)->toBe(SendWorkerPushAction::GONE)
        ->and($device->fresh()->push_endpoint)->toBeNull();
});

it('bewaart het abonnement alleen op de gekoppelde gsm', function () {
    [$tenant, , $worker, $device] = pushWorker('https://push.example.test/keep');
    $endpoint = 'https://push.example.test/new';

    $saved = app(SaveWorkerPushSubscriptionAction::class)->handle(
        $device->device_token,
        $endpoint,
        'publickeyvalue',
        'authtokenvalue',
        'nl',
    );

    expect($saved)->toBeTrue()
        ->and($device->fresh()->push_endpoint)->toBe($endpoint);

    $other = WorkerDevice::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
    ]);

    expect(app(SaveWorkerPushSubscriptionAction::class)->handle(
        $other->device_token,
        'https://push.example.test/other',
        'publickeyvalue',
        'authtokenvalue',
        'nl',
    ))->toBeFalse();

    Tenancy::forget();

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->withCookie(WorkerDeviceSession::DEVICE_TOKEN_COOKIE, $device->device_token)
        ->post(route('public.time-portal.push-subscription'), [
            'endpoint' => 'https://push.example.test/http',
            'public_key' => 'publickeyvalue',
            'auth_token' => 'authtokenvalue',
            'locale' => 'nl',
        ]);

    expect($response->status())->toBe(204);
});

it('geeft de open-parameter door naar het portaal', function () {
    $tenant = Tenant::factory()->create();
    $clock = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'is_active' => true,
    ]);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1')
        ->get(route('public.time-portal.home', ['tenant' => $tenant->id, 'open' => 'documents']))
        ->assertRedirect(route('public.time-portal.cp', ['token' => $clock->qr_token, 'open' => 'documents']));

    $this->get(route('public.time-portal.home', ['tenant' => $tenant->id, 'open' => 'nope']))
        ->assertRedirect(route('public.time-portal.cp', $clock->qr_token));
});

it('pusht een persoonlijk toegewezen taak één keer naar die uitvoerder', function () {
    [$tenant, $team, $worker] = pushWorker('https://push.example.test/task');
    $issue = App\Models\Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => now()]);

    $task = app(App\Actions\Tasks\CreateTaskAction::class)->handle($issue, $team->id, assignedWorkerId: $worker->id);
    app(App\Actions\Tasks\UpdateTaskAssignmentAction::class)->handle($task, $team->id, $worker->id);

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['payload']['title'])->toBe('Nieuwe taak voor jou');
});

it('pusht een teamtaak gebundeld en pas na goedkeuring van de melding', function () {
    [$tenant, $team] = pushWorker('https://push.example.test/team-task');
    $issue = App\Models\Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => null]);
    $create = app(App\Actions\Tasks\CreateTaskAction::class);

    $create->handle($issue, $team->id, duringIssueIntake: true);
    expect($this->push->sent)->toHaveCount(0);

    app(App\Actions\Issues\ApproveIssueAction::class)->handle($issue, User::factory()->admin()->create(['tenant_id' => $tenant->id]));
    $create->handle($issue->fresh(), $team->id);

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['payload']['title'])->toBe('Nieuwe taak voor je team');
});

it('pusht wederkerende taken als één ochtendmelding per uitvoerder', function () {
    [$tenant, $team, $worker] = pushWorker('https://push.example.test/recurring');
    $issue = App\Models\Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => now()]);
    $tasks = collect(range(1, 3))->map(fn () => App\Models\Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'is_recurring_cycle' => true,
    ]))->all();

    app(App\Actions\Tasks\NotifyWorkersOfRecurringTasksAction::class)->handle($tenant, $tasks);

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['payload']['title'])->toBe('Taken voor vandaag')
        ->and($this->push->sent[0]['payload']['body'])->toContain('3');
});
