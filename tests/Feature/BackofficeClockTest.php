<?php

use App\Actions\Team\CreateLinkedWorkerForUserAction;
use App\Enums\ClockSource;
use App\Livewire\Time\BackofficeClock;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Livewire\Livewire;

it('toont inklokken en pauze naast elkaar en wisselt de labels', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'time_backoffice_clock' => true,
        'time_gps_on_clock' => true,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Anne Admin',
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    app(CreateLinkedWorkerForUserAction::class)->handle($admin, $team, [], (int) $admin->id);
    $admin->unsetRelation('linkedWorker');
    $admin->unsetRelation('tenant');

    expect($admin->tenant->allowsBackofficeClock())->toBeTrue()
        ->and($admin->linkedWorker)->not->toBeNull()
        ->and($admin->can('clockFromBackoffice', $admin->tenant))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('wp-sidebar-clock', false)
        ->assertSee(__('time.portal.clock.in'), false)
        ->assertSee(__('time.backoffice_clock.start_break'), false)
        ->assertSee(__('common.button.logout'), false)
        ->assertDontSee('Uitloggen', false);

    $workerId = (int) $admin->fresh()->linkedWorker->id;

    Livewire::actingAs($admin)
        ->test(BackofficeClock::class)
        ->call('clock')
        ->assertSee(__('time.portal.clock.out'), false)
        ->assertSee(__('time.backoffice_clock.start_break'), false)
        ->call('toggleBreak')
        ->assertSee(__('time.backoffice_clock.end_break'), false)
        ->call('toggleBreak')
        ->assertSee(__('time.backoffice_clock.start_break'), false)
        ->call('clock')
        ->assertSee(__('time.portal.clock.in'), false);

    $shift = WorkShift::query()->where('worker_id', $workerId)->first();
    expect($shift)->not->toBeNull()
        ->and($shift->clock_in_source)->toBe(ClockSource::Backoffice)
        ->and($shift->clock_out_source)->toBe(ClockSource::Backoffice)
        ->and($shift->clock_in_device_id)->toBeNull()
        ->and($shift->clock_in_latitude)->toBeNull();
});

it('verbergt de zijbalk-inklok zonder instelling of zonder prikklok-team', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'time_backoffice_clock' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    app(CreateLinkedWorkerForUserAction::class)->handle($admin, $team, [], (int) $admin->id);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('wp-sidebar-clock', false);

    $tenant->update(['time_backoffice_clock' => true]);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('wp-sidebar-clock', false);

    app(CreateLinkedWorkerForUserAction::class)->handle($employee, $team, [], (int) $admin->id);

    $this->actingAs($employee->fresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('wp-sidebar-clock', false);
});
