<?php

use App\Actions\Retention\ArchiveDueTenantTimeYearsAction;
use App\Actions\Retention\ArchiveTenantTimeYearAction;
use App\Actions\Retention\PruneAbsenceRequestPhotosAction;
use App\Actions\Retention\PruneExpiredTimeArchivesAction;
use App\Enums\BreakType;
use App\Enums\PresenceComplianceScope;
use App\Enums\PresenceSourceEvent;
use App\Enums\PresenceSubmissionStatus;
use App\Enums\PresenceType;
use App\Livewire\Dashboard;
use App\Livewire\Pages\Subscription;
use App\Models\AbsenceRequest;
use App\Models\AbsenceRequestPhoto;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\TenantTimeArchive;
use App\Models\User;
use App\Models\WorkBreak;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use App\Support\Time\TimeArchiveCsv;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(function () {
    Carbon::setTestNow();
});

function timeArchiveTenant(array $extra = []): array
{
    $tenant = Tenant::factory()->create(array_merge(['has_time_module' => true], $extra));
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ploeg A']);
    $clock = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Poort',
        'qr_token' => 'arc-clock-'.$tenant->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'is_active' => true,
    ]);

    return [$tenant, $admin, $team, $clock, $worker];
}

function timeArchiveCsv(string $path, string $name): string
{
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($path)))->toBeTrue();
    $contents = $zip->getFromName($name);
    $zip->close();
    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

it('archiveert op 1 februari het jaar van twee jaar eerder en laat het vorige jaar staan', function () {
    Storage::fake('local');
    [$tenant, $admin, $team, $clock, $worker] = timeArchiveTenant();
    seedTenantPastOnboarding($tenant);

    $oldPlan = PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2025-06-15',
        'description' => 'Hal schoon',
    ]);
    $keptPlan = PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-03-02',
        'description' => 'Blijft zichtbaar',
    ]);
    $closed = WorkShift::factory()->closed()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clock->id,
        'clock_in_at' => Carbon::parse('2025-06-15 08:00:00', 'Europe/Brussels'),
        'clock_out_at' => Carbon::parse('2025-06-15 16:00:00', 'Europe/Brussels'),
    ]);
    $break = WorkBreak::query()->create([
        'tenant_id' => $tenant->id,
        'work_shift_id' => $closed->id,
        'started_at' => Carbon::parse('2025-06-15 12:00:00', 'Europe/Brussels'),
        'ended_at' => Carbon::parse('2025-06-15 12:30:00', 'Europe/Brussels'),
        'break_type' => BreakType::Break,
    ]);
    $open = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clock->id,
        'clock_in_at' => Carbon::parse('2025-12-20 08:00:00', 'Europe/Brussels'),
        'status' => 'open',
        'clock_out_at' => null,
    ]);
    $goneRequest = AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'date_from' => '2025-05-01',
        'date_to' => '2025-05-02',
    ]);
    $spanning = AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'date_from' => '2025-12-20',
        'date_to' => '2026-01-03',
    ]);
    $ciao = PresenceSubmission::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_shift_id' => $closed->id,
        'source_event' => PresenceSourceEvent::ClockIn,
        'presence_type' => PresenceType::In,
        'scope' => PresenceComplianceScope::CiaoCleaning,
        'registration_at' => Carbon::parse('2025-06-15 08:00:00', 'Europe/Brussels'),
        'status' => PresenceSubmissionStatus::Submitted,
    ]);
    $other = Tenant::factory()->create(['has_time_module' => true]);
    $otherPlan = PlannedShift::factory()->create([
        'tenant_id' => $other->id,
        'worker_id' => Worker::factory()->create(['tenant_id' => $other->id]),
        'work_date' => '2025-04-01',
    ]);

    $january = app(ArchiveTenantTimeYearAction::class)->handle(
        $tenant,
        2025,
        false,
        Carbon::parse('2027-01-31 23:00:00', 'Europe/Brussels'),
    );
    expect($january['reason'])->toBe('year_still_visible')
        ->and(PlannedShift::query()->withoutGlobalScopes()->whereKey($oldPlan->id)->exists())->toBeTrue();

    $tooSoon = app(ArchiveTenantTimeYearAction::class)->handle(
        $tenant,
        2026,
        false,
        Carbon::parse('2027-02-01 03:45:00', 'Europe/Brussels'),
    );
    expect($tooSoon['reason'])->toBe('year_still_visible')
        ->and(PlannedShift::query()->withoutGlobalScopes()->whereKey($keptPlan->id)->exists())->toBeTrue();

    $result = app(ArchiveTenantTimeYearAction::class)->handle(
        $tenant,
        2025,
        false,
        Carbon::parse('2027-02-01 03:45:00', 'Europe/Brussels'),
    );

    expect($result['skipped'])->toBeFalse()
        ->and($result['deleted']['planned'])->toBe(1)
        ->and($result['deleted']['shifts'])->toBe(1)
        ->and($result['deleted']['requests'])->toBe(1)
        ->and(PlannedShift::query()->withoutGlobalScopes()->whereKey($oldPlan->id)->exists())->toBeFalse()
        ->and(PlannedShift::query()->withoutGlobalScopes()->whereKey($keptPlan->id)->exists())->toBeTrue()
        ->and(PlannedShift::query()->withoutGlobalScopes()->whereKey($otherPlan->id)->exists())->toBeTrue()
        ->and(WorkShift::query()->withoutGlobalScopes()->whereKey($closed->id)->exists())->toBeFalse()
        ->and(WorkShift::query()->withoutGlobalScopes()->whereKey($open->id)->exists())->toBeTrue()
        ->and(WorkBreak::query()->withoutGlobalScopes()->whereKey($break->id)->exists())->toBeFalse()
        ->and(AbsenceRequest::query()->withoutGlobalScopes()->whereKey($goneRequest->id)->exists())->toBeFalse()
        ->and(AbsenceRequest::query()->withoutGlobalScopes()->whereKey($spanning->id)->exists())->toBeTrue()
        ->and(PresenceSubmission::query()->withoutGlobalScopes()->whereKey($ciao->id)->exists())->toBeTrue()
        ->and(PresenceSubmission::query()->withoutGlobalScopes()->whereKey($ciao->id)->value('work_shift_id'))->toBeNull();

    $archive = TenantTimeArchive::query()->where('tenant_id', $tenant->id)->where('kind', 'year')->first();
    expect($archive)->not->toBeNull();
    $roster = timeArchiveCsv($archive->path, 'rooster.csv');
    $shifts = timeArchiveCsv($archive->path, 'uren.csv');
    expect($roster)->toContain('Jan Janssen')
        ->and($roster)->toContain('Hal schoon')
        ->and($roster)->not->toContain('Blijft zichtbaar')
        ->and($shifts)->toContain('Jan Janssen')
        ->and(TimeArchiveCsv::decode($roster))->not->toBeEmpty();

    app(ArchiveTenantTimeYearAction::class)->handle(
        $tenant,
        2025,
        false,
        Carbon::parse('2027-02-02 03:45:00', 'Europe/Brussels'),
    );
    expect(PlannedShift::query()->withoutGlobalScopes()->whereKey($keptPlan->id)->exists())->toBeTrue()
        ->and(WorkShift::query()->withoutGlobalScopes()->whereKey($open->id)->exists())->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2027-02-02 10:00:00', 'Europe/Brussels'));
    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.time_archives.year_title'))
        ->assertSee(__('dashboard.time_archives.year_body', ['year' => 2025]));

    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->assertSee(__('subscription.time_archives.year', ['year' => 2025]))
        ->assertSee('31-01-2030');

    $this->actingAs($admin)
        ->get(route('subscription.time-archives.download', $archive))
        ->assertOk();

    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($employee)
        ->get(route('subscription.time-archives.download', $archive))
        ->assertForbidden();

    Carbon::setTestNow(Carbon::parse('2027-03-02 10:00:00', 'Europe/Brussels'));
    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertDontSee(__('dashboard.time_archives.year_title'));
});

it('weigert te wissen als het zipbestand niet gelezen kan worden', function () {
    Storage::fake('local');
    [$tenant, , , , $worker] = timeArchiveTenant();
    $plan = PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2025-06-15',
    ]);
    Storage::disk('local')->put('time-archives/'.$tenant->id.'/jaar-2025.zip', 'geen-zip');

    expect(fn () => app(ArchiveTenantTimeYearAction::class)->handle(
        $tenant,
        2025,
        false,
        Carbon::parse('2027-02-01 04:00:00', 'Europe/Brussels'),
    ))->toThrow(RuntimeException::class);

    expect(PlannedShift::query()->withoutGlobalScopes()->whereKey($plan->id)->exists())->toBeTrue();
});

it('houdt een aanvraag met een foto tot de foto in het maandbestand zit', function () {
    Storage::fake('local');
    [$tenant, $admin, , , $worker] = timeArchiveTenant();
    seedTenantPastOnboarding($tenant);
    $request = AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'date_from' => '2025-05-01',
        'date_to' => '2025-05-01',
    ]);
    $path = 'absence-request-photos/'.$tenant->id.'/brief.jpg';
    Storage::disk('local')->put($path, 'foto-bytes-123');
    $photo = AbsenceRequestPhoto::query()->create([
        'tenant_id' => $tenant->id,
        'absence_request_id' => $request->id,
        'path' => $path,
    ]);
    $photo->forceFill(['created_at' => Carbon::parse('2026-04-01 10:00:00', 'Europe/Brussels')])->save();

    app(ArchiveTenantTimeYearAction::class)->handle(
        $tenant,
        2025,
        false,
        Carbon::parse('2027-02-01 04:00:00', 'Europe/Brussels'),
    );
    expect(AbsenceRequest::query()->withoutGlobalScopes()->whereKey($request->id)->exists())->toBeTrue()
        ->and(AbsenceRequestPhoto::query()->withoutGlobalScopes()->whereKey($photo->id)->exists())->toBeTrue();

    $stats = app(PruneAbsenceRequestPhotosAction::class)->handle(false, Carbon::parse('2026-10-02 10:00:00', 'Europe/Brussels'));
    expect($stats['removed'])->toBe(1)
        ->and(AbsenceRequestPhoto::query()->withoutGlobalScopes()->whereKey($photo->id)->exists())->toBeFalse()
        ->and(AbsenceRequest::query()->withoutGlobalScopes()->whereKey($request->id)->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists($path))->toBeFalse();

    $photoArchive = TenantTimeArchive::query()->where('kind', 'photos')->first();
    expect($photoArchive)->not->toBeNull();
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($photoArchive->path)))->toBeTrue();
    expect($zip->getFromName('fotos/'.$photo->id.'.jpg'))->toBe('foto-bytes-123');
    $zip->close();

    Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', 'Europe/Brussels'));
    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.time_archives.photos_title'));

    app(ArchiveTenantTimeYearAction::class)->handle(
        $tenant,
        2025,
        false,
        Carbon::parse('2027-02-01 04:00:00', 'Europe/Brussels'),
    );
    expect(AbsenceRequest::query()->withoutGlobalScopes()->whereKey($request->id)->exists())->toBeFalse();

    $expired = app(PruneExpiredTimeArchivesAction::class)->handle(false, Carbon::parse('2026-11-15 10:00:00', 'Europe/Brussels'));
    expect($expired['removed'])->toBe(0)
        ->and(TenantTimeArchive::query()->whereKey($photoArchive->id)->exists())->toBeTrue();

    app(PruneExpiredTimeArchivesAction::class)->handle(false, Carbon::parse('2026-12-01 00:00:00', 'Europe/Brussels'));
    expect(TenantTimeArchive::query()->whereKey($photoArchive->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($photoArchive->path))->toBeFalse();
});

it('slaat een foto over als het bestand ontbreekt', function () {
    Storage::fake('local');
    [$tenant, $admin, , , $worker] = timeArchiveTenant();
    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->assertSee(__('subscription.time_archives.heading'))
        ->assertSee(__('subscription.time_archives.intro'))
        ->assertSee(__('subscription.time_archives.empty'));
    $request = AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'date_from' => '2026-01-02',
        'date_to' => '2026-01-02',
    ]);
    $photo = AbsenceRequestPhoto::query()->create([
        'tenant_id' => $tenant->id,
        'absence_request_id' => $request->id,
        'path' => 'absence-request-photos/'.$tenant->id.'/weg.jpg',
    ]);
    $photo->forceFill(['created_at' => Carbon::parse('2026-01-01 10:00:00')])->save();

    $stats = app(PruneAbsenceRequestPhotosAction::class)->handle(false, Carbon::parse('2026-08-01 10:00:00'));

    expect($stats['removed'])->toBe(0)
        ->and($stats['skipped'])->toBe(1)
        ->and(AbsenceRequestPhoto::query()->withoutGlobalScopes()->whereKey($photo->id)->exists())->toBeTrue();
});

it('archiveert checkmate-uren en laat een tenant zonder time met rust', function () {
    Storage::fake('local');
    [$checkmate, $cmAdmin, $team, $clock, $worker] = timeArchiveTenant([
        'has_time_module' => false,
        'checkmate_mode' => true,
    ]);
    $shift = WorkShift::factory()->closed()->create([
        'tenant_id' => $checkmate->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clock->id,
        'clock_in_at' => Carbon::parse('2025-04-04 08:00:00', 'Europe/Brussels'),
        'clock_out_at' => Carbon::parse('2025-04-04 12:00:00', 'Europe/Brussels'),
    ]);
    $plain = Tenant::factory()->create(['has_time_module' => false, 'checkmate_mode' => false]);
    $plainAdmin = User::factory()->admin()->create(['tenant_id' => $plain->id, 'locale' => 'nl']);
    $plainWorker = Worker::factory()->create(['tenant_id' => $plain->id]);
    $plainPlan = PlannedShift::factory()->create([
        'tenant_id' => $plain->id,
        'worker_id' => $plainWorker->id,
        'work_date' => '2025-04-04',
    ]);

    $stats = app(ArchiveDueTenantTimeYearsAction::class)->handle(
        false,
        Carbon::parse('2027-02-01 04:00:00', 'Europe/Brussels'),
    );

    expect($stats['errors'])->toBe(0)
        ->and(WorkShift::query()->withoutGlobalScopes()->whereKey($shift->id)->exists())->toBeFalse()
        ->and(PlannedShift::query()->withoutGlobalScopes()->whereKey($plainPlan->id)->exists())->toBeTrue()
        ->and(TenantTimeArchive::query()->where('tenant_id', $checkmate->id)->where('kind', 'year')->exists())->toBeTrue()
        ->and(TenantTimeArchive::query()->where('tenant_id', $plain->id)->exists())->toBeFalse();

    Livewire::actingAs($cmAdmin)
        ->test(Subscription::class)
        ->assertSee(__('subscription.time_archives.year', ['year' => 2025]));

    Livewire::actingAs($plainAdmin)
        ->test(Subscription::class)
        ->assertDontSee(__('subscription.time_archives.empty'))
        ->assertDontSee(__('subscription.time_archives.intro'));
});
