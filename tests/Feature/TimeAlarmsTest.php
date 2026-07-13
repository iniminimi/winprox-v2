<?php

declare(strict_types=1);

use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Tenancy;

it('laat een admin het time-alarmenscherm openen', function () {
    [$tenant, $admin] = (function () {
        $tenant = Tenant::factory()->create(['has_time_module' => true]);
        Tenancy::actAs($tenant->id);
        $admin = \App\Models\User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => \App\Models\User::ROLE_ADMIN,
        ]);

        return [$tenant, $admin];
    })();

    $this->actingAs($admin)
        ->get(route('time.alarms.index'))
        ->assertOk()
        ->assertSee(__('time.alarms.title'), false);
});

it('toont alarmen voor lange open shifts', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    config(['time.long_shift_hours' => 8]);

    $admin = \App\Models\User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => \App\Models\User::ROLE_ADMIN,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);

    $shift = app(\App\Actions\Time\ClockInAction::class)->handle($worker, $clockPoint);
    $shift->update(['clock_in_at' => now()->subHours(9)]);

    $this->actingAs($admin)
        ->get(route('time.alarms.index'))
        ->assertOk()
        ->assertSee($worker->displayName(), false);
});
