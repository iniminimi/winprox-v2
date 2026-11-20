<?php

declare(strict_types=1);

use App\Actions\Time\AutoCloseStaleWorkShiftsAction;
use App\Actions\Time\CountTimePresenceAttentionAction;
use App\Enums\TimePresenceAttentionType;
use App\Enums\WorkShiftStatus;
use App\Livewire\Pages\Team as TeamPage;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use App\Support\Time\TimePresenceAttentionRules;
use Livewire\Livewire;

function shiftThresholdTenant(array $attrs = []): array
{
    $tenant = Tenant::factory()->create(array_merge([
        'has_time_module' => true,
        'checkmate_mode' => false,
    ], $attrs));
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    return [$tenant, $admin];
}

it('sluit open shifts op de stale-drempel van het team', function () {
    [$tenant] = shiftThresholdTenant();
    config(['time.stale_shift_hours' => 16]);

    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'stale_shift_hours' => 12,
        'long_shift_hours' => 8,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $shift = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_in_at' => now()->subHours(13),
    ]);

    $closed = app(AutoCloseStaleWorkShiftsAction::class)->handle();

    expect($closed)->toBe(1)
        ->and($shift->fresh()->status)->toBe(WorkShiftStatus::ForceClosed);
});

it('houdt Checkmate op de globale auto-close-drempel', function () {
    [$tenant] = shiftThresholdTenant(['checkmate_mode' => true]);
    config(['time.stale_shift_hours' => 16]);

    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'stale_shift_hours' => 8,
        'long_shift_hours' => 4,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $shift = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_in_at' => now()->subHours(10),
    ]);

    $closed = app(AutoCloseStaleWorkShiftsAction::class)->handle();

    expect($closed)->toBe(0)
        ->and($shift->fresh()->status)->toBe(WorkShiftStatus::Open);
});

it('gebruikt de lange-shift-drempel van het team voor alarmen', function () {
    [$tenant] = shiftThresholdTenant();
    config(['time.long_shift_hours' => 10, 'time.stale_shift_hours' => 16]);

    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'stale_shift_hours' => 16,
        'long_shift_hours' => 6,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $shift = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_in_at' => now()->subHours(7),
    ]);
    $shift->load(['team', 'breaks', 'openBreak']);

    $items = TimePresenceAttentionRules::collect(collect([$shift]), false);

    expect($items)->toHaveCount(1)
        ->and($items->first()->type)->toBe(TimePresenceAttentionType::LongShift)
        ->and($items->first()->thresholdHours)->toBe(6)
        ->and(app(CountTimePresenceAttentionAction::class)->handle($tenant->id))->toBe(1);
});

it('weigert een lange-shift-drempel die niet lager is dan auto-close', function () {
    [$tenant, $admin] = shiftThresholdTenant();
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'stale_shift_hours' => 16,
        'long_shift_hours' => 10,
    ]);

    Livewire::actingAs($admin)
        ->test(TeamPage::class)
        ->call('openEditTeam', $team->id)
        ->set('teamLongShiftHours', 16)
        ->set('teamStaleShiftHours', 16)
        ->call('saveTeam')
        ->assertHasErrors(['teamLongShiftHours']);

    expect($team->fresh()->long_shift_hours)->toBe(10);
});

it('slaat teamdrempels op tot maximaal 24 uur', function () {
    [$tenant, $admin] = shiftThresholdTenant();
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'stale_shift_hours' => 16,
        'long_shift_hours' => 10,
    ]);

    Livewire::actingAs($admin)
        ->test(TeamPage::class)
        ->call('openEditTeam', $team->id)
        ->set('teamLongShiftHours', 18)
        ->set('teamStaleShiftHours', 24)
        ->call('saveTeam')
        ->assertHasNoErrors();

    $team = $team->fresh();
    expect($team->long_shift_hours)->toBe(18)
        ->and($team->stale_shift_hours)->toBe(24)
        ->and($team->staleShiftHours())->toBe(24);
});
