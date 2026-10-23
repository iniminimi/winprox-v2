<?php

use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;

function teamSchedulePortalTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Poort Zuid',
        'location_id' => $location->id,
        'qr_token' => 'team-schedule-'.$tenant->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $worker->locations()->attach($location->id);

    return [$tenant, $team, $clockPoint, $worker, $location];
}

function teamScheduleSignIn(ClockPoint $clockPoint): mixed
{
    return Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon');
}

function teamScheduleShift(Tenant $tenant, Worker $worker, string $date, array $overrides = []): PlannedShift
{
    return PlannedShift::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
        'start_time' => '07:00',
        'end_time' => '15:00',
    ], $overrides));
}

it('toont het teamrooster met collega’s van dezelfde locatie', function () {
    $this->travelTo(Carbon::parse('2026-10-19 12:00:00')); // maandag week 43
    [$tenant, $team, $clockPoint, $worker, $location] = teamSchedulePortalTenant();
    $colleague = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Iris',
        'last_name' => 'Vandamme',
    ]);
    $colleague->locations()->attach($location->id);

    teamScheduleShift($tenant, $colleague, '2026-10-20', [
        'start_time' => '07:00', 'end_time' => '15:00', 'unit_name' => 'Kapel',
        'location_id' => $location->id,
    ]);
    teamScheduleShift($tenant, $worker, '2026-10-20', [
        'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    teamScheduleShift($tenant, $colleague, '2026-10-20', [
        'start_time' => '18:00', 'end_time' => '22:00', 'status' => PlannedShiftStatus::Draft,
    ]);

    teamScheduleSignIn($clockPoint)
        ->assertSee(__('time.portal.team_schedule.tile'), false)
        ->call('openTeamSchedule')
        ->assertSet('teamScheduleOpen', true)
        ->assertSee('Week 43', false)
        ->assertSee('Iris Vandamme', false)
        ->assertSee('Kapel', false)
        ->assertSee('07:00-15:00', false)
        ->assertSee(__('time.portal.team_schedule.you'), false)
        ->assertDontSee('18:00-22:00', false); // draft nooit zichtbaar
});

it('verbergt collega’s zonder band met de locatie en toont invallers', function () {
    $this->travelTo(Carbon::parse('2026-10-19 12:00:00'));
    [$tenant, $team, $clockPoint, $worker, $location] = teamSchedulePortalTenant();
    $other = Location::factory()->create(['tenant_id' => $tenant->id]);

    // Collega op een ándere locatie: niet zichtbaar.
    $away = Worker::factory()->create([
        'tenant_id' => $tenant->id, 'internal_team_id' => $team->id,
        'first_name' => 'Weg', 'last_name' => 'Collega',
    ]);
    $away->locations()->attach($other->id);
    teamScheduleShift($tenant, $away, '2026-10-20', ['location_id' => $other->id]);

    // Invaller: niet toegewezen maar wél dienst op onze locatie → zichtbaar.
    $flex = Worker::factory()->create([
        'tenant_id' => $tenant->id, 'internal_team_id' => $team->id,
        'first_name' => 'Flex', 'last_name' => 'Invaller',
    ]);
    teamScheduleShift($tenant, $flex, '2026-10-21', [
        'location_id' => $location->id, 'unit_name' => 'Entree',
    ]);

    teamScheduleSignIn($clockPoint)
        ->call('openTeamSchedule')
        ->assertSee('Flex Invaller', false)
        ->assertSee('Entree', false)
        ->assertDontSee('Weg Collega', false);
});

it('toont afwezigheden van collega’s neutraal', function () {
    $this->travelTo(Carbon::parse('2026-10-19 12:00:00'));
    [$tenant, $team, $clockPoint, $worker, $location] = teamSchedulePortalTenant();
    $colleague = Worker::factory()->create([
        'tenant_id' => $tenant->id, 'internal_team_id' => $team->id,
        'first_name' => 'Zieke', 'last_name' => 'Collega',
    ]);
    $colleague->locations()->attach($location->id);

    teamScheduleShift($tenant, $colleague, '2026-10-22', [
        'kind' => ShiftTypeKind::Sick, 'start_time' => null, 'end_time' => null,
    ]);

    teamScheduleSignIn($clockPoint)
        ->call('openTeamSchedule')
        ->assertSee(__('time.portal.team_schedule.absent'), false)
        ->assertSee('Zieke Collega', false)
        ->assertDontSee(__('time.schedule.types.kinds.'.ShiftTypeKind::Sick->value), false);
});

it('verbergt de tegel als de tenant het teamrooster uitschakelt', function () {
    $this->travelTo(Carbon::parse('2026-10-19 12:00:00'));
    [$tenant, $team, $clockPoint] = teamSchedulePortalTenant();
    $tenant->update(['time_team_roster' => false]);

    teamScheduleSignIn($clockPoint)
        ->assertDontSee(__('time.portal.team_schedule.tile'), false)
        ->call('openTeamSchedule')
        ->assertSet('teamScheduleOpen', false);
});

it('navigeert tussen weken', function () {
    $this->travelTo(Carbon::parse('2026-10-19 12:00:00'));
    [$tenant, $team, $clockPoint, $worker, $location] = teamSchedulePortalTenant();
    $colleague = Worker::factory()->create([
        'tenant_id' => $tenant->id, 'internal_team_id' => $team->id,
        'first_name' => 'Later', 'last_name' => 'Week',
    ]);
    $colleague->locations()->attach($location->id);
    teamScheduleShift($tenant, $colleague, '2026-10-27');

    teamScheduleSignIn($clockPoint)
        ->call('openTeamSchedule')
        ->assertSet('teamScheduleWeek', '2026-10-19')
        ->assertDontSee('Later Week', false)
        ->call('nextTeamScheduleWeek')
        ->assertSet('teamScheduleWeek', '2026-10-26')
        ->assertSee('Later Week', false)
        ->call('thisTeamScheduleWeek')
        ->assertSet('teamScheduleWeek', '2026-10-19');
});
