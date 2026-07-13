<?php

declare(strict_types=1);

use App\Actions\Time\ClockInAction;
use App\Actions\Time\CountTimePresenceAttentionAction;
use App\Livewire\Time\AlarmsIndex;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

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

    expect(app(CountTimePresenceAttentionAction::class)->handle($tenant->id))->toBe(1);
});

it('toont alarm-badge in time-nav op urenscherm', function () {
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
        ->get(route('time.shifts.index'))
        ->assertOk()
        ->assertSee('wp-time-nav__alarm-count', false);
});

it('toont alarmen met load-more bij grote lijsten', function () {
    config(['time.presence_team_page_size' => 3, 'time.stale_shift_hours' => 16]);

    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);

    $admin = \App\Models\User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => \App\Models\User::ROLE_ADMIN,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    for ($i = 0; $i < 6; $i++) {
        $worker = Worker::factory()->create([
            'tenant_id' => $tenant->id,
            'internal_team_id' => $team->id,
        ]);
        $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
        $shift->update(['clock_in_at' => now()->subHours(17)]);
    }

    Livewire::actingAs($admin)
        ->test(AlarmsIndex::class)
        ->assertSee(__('time.alarms.shown', ['shown' => 3, 'total' => 6]), false)
        ->call('loadMore')
        ->assertDontSee(__('time.presence.load_more', ['count' => 1]), false);
});

it('filtert alarmen op type zonder blade-fout', function () {
    config(['time.stale_shift_hours' => 16, 'time.long_shift_hours' => 10]);

    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);

    $admin = \App\Models\User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => \App\Models\User::ROLE_ADMIN,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $staleWorker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);
    $staleShift = app(ClockInAction::class)->handle($staleWorker, $clockPoint);
    $staleShift->update(['clock_in_at' => now()->subHours(17)]);

    $longWorker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);
    $longShift = app(ClockInAction::class)->handle($longWorker, $clockPoint);
    $longShift->update(['clock_in_at' => now()->subHours(11)]);

    Livewire::actingAs($admin)
        ->test(AlarmsIndex::class)
        ->call('setAttentionType', 'stale_shift')
        ->assertSet('attentionType', 'stale_shift')
        ->assertSee($staleWorker->displayName(), false)
        ->assertDontSee($longWorker->displayName(), false);
});
