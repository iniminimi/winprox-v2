<?php

declare(strict_types=1);

use App\Actions\Time\ListWorkerHoursAction;
use App\Enums\WorkShiftStatus;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function hoursPortalTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Poort Noord',
        'qr_token' => 'hours-clock-'.$tenant->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);

    return [$tenant, $team, $clockPoint, $worker];
}

function signInHoursWorker(ClockPoint $clockPoint): mixed
{
    return Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon');
}

it('toont de tegel Mijn uren na aanmelden op Clock Point', function () {
    [, , $clockPoint] = hoursPortalTenant();

    signInHoursWorker($clockPoint)
        ->assertSee(__('time.portal.hours.tile'), false)
        ->assertSee(__('time.portal.hours.tile_sub'), false)
        ->assertSet('hoursListOpen', false);
});

it('verbergt Mijn uren zonder time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'no-hours-clock',
    ]);
    Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);

    signInHoursWorker($clockPoint)
        ->assertDontSeeHtml('wire:click="openHours"');
});

it('toont alleen de eigen diensten van de aangemelde uitvoerder', function () {
    [$tenant, $team, $clockPoint, $worker] = hoursPortalTenant();
    $otherPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Poort Geheim',
    ]);
    $other = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Bram',
        'last_name' => 'Buiten',
        'field_icon_slug' => 'leaf',
    ]);

    $day = now()->startOfMonth()->addDays(2)->setTime(8, 0);
    WorkShift::factory()->closed()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_out_clock_point_id' => $clockPoint->id,
        'clock_in_at' => $day,
        'clock_out_at' => $day->copy()->setTime(16, 30),
        'total_break_minutes' => 30,
        'status' => WorkShiftStatus::Closed,
    ]);
    WorkShift::factory()->closed()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $other->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $otherPoint->id,
        'clock_out_clock_point_id' => $otherPoint->id,
        'clock_in_at' => $day->copy()->setTime(9, 0),
        'clock_out_at' => $day->copy()->setTime(17, 0),
        'status' => WorkShiftStatus::Closed,
    ]);

    signInHoursWorker($clockPoint)
        ->call('openHours')
        ->assertSet('hoursListOpen', true)
        ->assertSee(__('time.portal.hours.title'), false)
        ->assertSee('Poort Noord', false)
        ->assertSee('08:00', false)
        ->assertSee('16:30', false)
        ->assertDontSee('Poort Geheim', false)
        ->assertDontSee('Bram Buiten', false);
});

it('toont een lege maand en bladert naar vorige maand', function () {
    [$tenant, $team, $clockPoint, $worker] = hoursPortalTenant();
    $lastMonth = now()->startOfMonth()->subMonth()->addDays(3)->setTime(7, 15);
    WorkShift::factory()->closed()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_out_clock_point_id' => $clockPoint->id,
        'clock_in_at' => $lastMonth,
        'clock_out_at' => $lastMonth->copy()->setTime(11, 15),
        'status' => WorkShiftStatus::Closed,
    ]);

    signInHoursWorker($clockPoint)
        ->call('openHours')
        ->assertSee(__('time.portal.hours.empty'), false)
        ->assertDontSee('07:15', false)
        ->call('previousHoursMonth')
        ->assertDontSee(__('time.portal.hours.empty'), false)
        ->assertSee('07:15', false)
        ->assertSee('11:15', false);
});

it('weigert de urenlijst via Livewire-state', function () {
    [, , $clockPoint] = hoursPortalTenant();
    $component = signInHoursWorker($clockPoint);

    expect(fn () => $component->set('hoursListOpen', true))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $component->assertSet('hoursListOpen', false)
        ->assertDontSee(__('time.portal.hours.empty'), false);
});

it('isoleert eigen uren per tenant in de Action', function () {
    [$tenantA, $teamA, $clockPointA, $workerA] = hoursPortalTenant();
    WorkShift::factory()->closed()->create([
        'tenant_id' => $tenantA->id,
        'worker_id' => $workerA->id,
        'internal_team_id' => $teamA->id,
        'clock_in_clock_point_id' => $clockPointA->id,
        'clock_out_clock_point_id' => $clockPointA->id,
        'clock_in_at' => now()->startOfMonth()->addDay()->setTime(8, 0),
        'clock_out_at' => now()->startOfMonth()->addDay()->setTime(12, 0),
        'status' => WorkShiftStatus::Closed,
    ]);

    $tenantB = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenantB->id);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenantB->id]);
    $clockPointB = ClockPoint::factory()->create(['tenant_id' => $tenantB->id]);
    $workerB = Worker::factory()->create([
        'tenant_id' => $tenantB->id,
        'internal_team_id' => $teamB->id,
    ]);
    WorkShift::factory()->closed()->create([
        'tenant_id' => $tenantB->id,
        'worker_id' => $workerB->id,
        'internal_team_id' => $teamB->id,
        'clock_in_clock_point_id' => $clockPointB->id,
        'clock_out_clock_point_id' => $clockPointB->id,
        'clock_in_at' => now()->startOfMonth()->addDay()->setTime(10, 0),
        'clock_out_at' => now()->startOfMonth()->addDay()->setTime(18, 0),
        'status' => WorkShiftStatus::Closed,
    ]);

    $hours = app(ListWorkerHoursAction::class)->handle(
        $workerB,
        (int) $tenantB->id,
        now()->startOfMonth(),
        now()->endOfMonth(),
    );

    expect($hours->shifts)->toHaveCount(1)
        ->and($hours->shifts->first()->worker_id)->toBe($workerB->id)
        ->and($hours->shifts->first()->clock_in_at->format('H:i'))->toBe('10:00');
});
