<?php

use App\Actions\Time\BuildTimePresenceDashboardAction;
use App\Actions\Time\ClockInAction;
use App\Livewire\Time\PresenceIndex;
use App\Livewire\Time\ShiftsIndex;
use App\Actions\Time\ClockOutAction;
use App\Actions\Time\EndWorkBreakAction;
use App\Actions\Time\EndWorkVisitAction;
use App\Actions\Time\ForceCloseWorkShiftAction;
use App\Actions\Time\StartWorkBreakAction;
use App\Actions\Time\StartWorkVisitAction;
use App\Actions\Time\SuggestNearbyClockUnitsAction;
use App\Actions\Units\RecordUnitGpsReportAction;
use App\Data\Units\RecordUnitGpsReportData;
use App\Enums\PresenceComplianceScope;
use App\Jobs\SubmitPresenceSubmissionJob;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\Worker;
use App\Models\WorkVisit;
use App\Support\Tenancy;
use Livewire\Livewire;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

afterEach(fn () => Tenancy::forget());

function gpsVisitContext(bool $ciao = false): array
{
    $attrs = [
        'has_time_module' => true,
        'time_gps_visits' => true,
        'time_gps_visit_radius_meters' => 250,
    ];
    if ($ciao) {
        $attrs = array_merge($attrs, [
            'presence_compliance_enabled' => true,
            'presence_compliance_scope' => PresenceComplianceScope::CiaoCleaning->value,
            'enterprise_number' => '0123456789',
            'presence_rsz_client_id' => 'test-client',
            'presence_rsz_private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEowIBAAKCAQEA0Z3VS5JJcds3xfn/ygWyF7PtvEj7pK8P0qK9nF0=\n-----END PRIVATE KEY-----",
        ]);
    }
    $tenant = Tenant::factory()->create($attrs);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'street' => 'Teststraat',
        'house_number' => '1',
        'postal_code' => '1000',
        'city' => 'Brussel',
        'contractual_relationship_reference' => $ciao ? '1Y1003SQ5VSSZ' : null,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'ssin' => $ciao ? '90010100123' : null,
    ]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'latitude' => 51.05,
        'longitude' => 3.73,
        'is_active' => true,
        'name' => 'Gebouw A',
    ]);

    return [$tenant, $worker, $clockPoint, $location, $unit];
}

it('opent een dienst zonder WorkVisit en zonder GPS-gate', function () {
    [$tenant, $worker, $clockPoint] = gpsVisitContext();

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);

    expect($shift->status->isOpen())->toBeTrue()
        ->and(WorkVisit::query()->count())->toBe(0)
        ->and($shift->clock_in_latitude)->toBeNull();
});

it('weigert een bezoek zonder open dienst', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();

    expect(fn () => app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73))
        ->toThrow(InvalidArgumentException::class, 'shift_not_open');
});

it('weigert werk starten buiten de straal, ook als de lijst eerder nabij was', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    app(ClockInAction::class)->handle($worker, $clockPoint);

    expect(fn () => app(StartWorkVisitAction::class)->handle($worker, $unit, 51.10, 3.73))
        ->toThrow(InvalidArgumentException::class, 'visit_unit_out_of_range');
});

it('start een bezoek op de unit-pin binnen de straal', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);

    $visit = app(StartWorkVisitAction::class)->handle($worker, $unit, 51.0501, 3.7301);

    expect($visit->work_shift_id)->toBe($shift->id)
        ->and($visit->unit_id)->toBe($unit->id)
        ->and($visit->isOpen())->toBeTrue()
        ->and($shift->fresh()->openVisit?->id)->toBe($visit->id);
});

it('wisselt A naar B met OUT A daarna IN B', function () {
    [$tenant, $worker, $clockPoint, $location, $unitA] = gpsVisitContext();
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'latitude' => 51.051,
        'longitude' => 3.731,
        'is_active' => true,
        'name' => 'Gebouw B',
    ]);
    app(ClockInAction::class)->handle($worker, $clockPoint);
    $visitA = app(StartWorkVisitAction::class)->handle($worker, $unitA, 51.05, 3.73);

    $visitB = app(StartWorkVisitAction::class)->handle($worker, $unitB, 51.051, 3.731);

    expect($visitA->fresh()->ended_at)->not->toBeNull()
        ->and($visitB->isOpen())->toBeTrue()
        ->and(WorkVisit::query()->open()->count())->toBe(1)
        ->and(WorkVisit::query()->open()->first()->unit_id)->toBe($unitB->id);
});

it('sluit een open bezoek bij uitklokken, ook ver van de pin', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    app(ClockInAction::class)->handle($worker, $clockPoint);
    $visit = app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);

    $shift = app(ClockOutAction::class)->handle($worker, $clockPoint);

    expect($shift->status->value)->toBe('closed')
        ->and($visit->fresh()->ended_at)->not->toBeNull()
        ->and(WorkVisit::query()->open()->count())->toBe(0);
});

it('laat een dienst met nul bezoeken uitklokken', function () {
    [$tenant, $worker, $clockPoint] = gpsVisitContext();
    app(ClockInAction::class)->handle($worker, $clockPoint);

    $shift = app(ClockOutAction::class)->handle($worker, $clockPoint);

    expect($shift->status->value)->toBe('closed')
        ->and(WorkVisit::query()->count())->toBe(0);
});

it('sluit een open bezoek bij force-close', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $visit = app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);

    app(ForceCloseWorkShiftAction::class)->handle($shift, 'Gsm kwijt', (int) $tenant->id, null);

    expect($visit->fresh()->ended_at)->not->toBeNull()
        ->and(WorkVisit::query()->open()->count())->toBe(0);
});

it('stelt alleen gepinde units voor en laat unit_gps_reports de pin met rust', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $unpinned = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'latitude' => null,
        'longitude' => null,
        'is_active' => true,
    ]);

    app(RecordUnitGpsReportAction::class)->handle(
        unit: $unit,
        data: new RecordUnitGpsReportData(
            latitude: 50.00,
            longitude: 4.00,
            reportedAt: CarbonImmutable::parse('2026-09-17T12:00:00+02:00'),
        ),
        tenantId: (int) $tenant->id,
        actorUserId: null,
        workerId: null,
    );

    expect(round((float) $unit->fresh()->latitude, 2))->toBe(51.05)
        ->and(round((float) $unit->fresh()->longitude, 2))->toBe(3.73)
        ->and($unpinned->fresh()->latitude)->toBeNull();

    $nearPin = app(SuggestNearbyClockUnitsAction::class)->handle($worker, 51.05, 3.73);
    $nearReport = app(SuggestNearbyClockUnitsAction::class)->handle($worker, 50.00, 4.00);

    expect(collect($nearPin)->pluck('unitId')->all())->toBe([(int) $unit->id])
        ->and($nearReport)->toBe([]);
});

it('enqueue geen CIAO bij clock in/out als GPS-bezoeken aan staan; visit start/end wel', function () {
    Queue::fake();
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext(ciao: true);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    expect(PresenceSubmission::query()->count())->toBe(0);

    app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);
    app(ClockOutAction::class)->handle($worker, $clockPoint);

    $events = PresenceSubmission::query()->orderBy('id')->pluck('source_event')->map->value->all();
    $types = PresenceSubmission::query()->orderBy('id')->pluck('presence_type')->map->value->all();

    expect($events)->toBe(['visit_start', 'visit_end'])
        ->and($types)->toBe(['IN', 'OUT'])
        ->and($shift->fresh()->id)->toBe($shift->id);
    Queue::assertPushed(SubmitPresenceSubmissionJob::class, 2);
});

it('houdt pauze OUT/IN tijdens een GPS-bezoek', function () {
    Queue::fake();
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext(ciao: true);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);
    app(StartWorkBreakAction::class)->handle($worker, $shift);
    app(EndWorkBreakAction::class)->handle($worker, $shift->fresh());
    app(EndWorkVisitAction::class)->handle($worker, required: true, latitude: 51.05, longitude: 3.73);

    $events = PresenceSubmission::query()->orderBy('id')->pluck('source_event')->map->value->all();
    $types = PresenceSubmission::query()->orderBy('id')->pluck('presence_type')->map->value->all();

    expect($events)->toBe(['visit_start', 'break_start', 'break_end', 'visit_end'])
        ->and($types)->toBe(['IN', 'OUT', 'IN', 'OUT']);
});

it('start en stopt een bezoek via de API met time:write', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    app(ClockInAction::class)->handle($worker, $clockPoint);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['time:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/time/work-visits/start', [
            'worker_id' => $worker->id,
            'unit_id' => $unit->id,
            'latitude' => 51.05,
            'longitude' => 3.73,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');

    expect(WorkVisit::query()->open()->where('worker_id', $worker->id)->exists())->toBeTrue();

    $this->withToken($token)
        ->postJson('/api/v1/time/work-visits/end', [
            'worker_id' => $worker->id,
            'latitude' => 51.05,
            'longitude' => 3.73,
        ])
        ->assertOk();

    expect(WorkVisit::query()->open()->count())->toBe(0);
});

it('toont het open werkbezoek op aanwezigheid en de historiek op uren', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);

    $dashboard = app(BuildTimePresenceDashboardAction::class)->handle(
        $tenant->id,
        expandedTeamIds: [(int) $worker->internal_team_id],
    );
    $openShift = $dashboard->teamBuckets
        ->flatMap(fn ($bucket) => $bucket->activeShifts)
        ->first();

    expect($openShift?->openVisit?->unit_id)->toBe($unit->id);

    Livewire::actingAs($admin)
        ->test(PresenceIndex::class)
        ->assertSee(__('time.presence.working_at', ['place' => $location->name.' · '.$unit->name]), false);

    Livewire::actingAs($admin)
        ->test(ShiftsIndex::class)
        ->assertSee(__('time.shifts.visits_heading'), false)
        ->assertSee($unit->name, false);
});

it('exposeert time.visit webhook-events', function () {
    expect(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.visit.started')
        ->and(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.visit.ended');
});
