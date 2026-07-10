<?php

declare(strict_types=1);

use App\Actions\Time\ClockInAction;
use App\Livewire\Pages\Team;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('verbergt time-navigatie zonder time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(__('common.nav.time'), false);
});

it('weigert time-beheerschermen zonder time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('time.presence.index'))
        ->assertForbidden();
});

it('laat time-beheerschermen toe met time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('time.presence.index'))
        ->assertOk();
});

it('toont altijd de clock point qr-knop op teams', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('team.index'))
        ->assertOk()
        ->assertSee(__('team.clock_point_qr.button'), false);
});

it('maakt een default clock point aan via de teams-knop zonder time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    Tenancy::actAs($tenant->id);
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openClockPointQr')
        ->assertRedirect(route('time.clock-points.qr', ClockPoint::query()->first()));

    expect(ClockPoint::query()->count())->toBe(1);
});

it('linkt de teams-knop naar clock points met time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('team.index'))
        ->assertOk()
        ->assertSee(route('time.clock-points.index'), false);
});

it('weigert inklokken zonder time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => app(ClockInAction::class)->handle($worker, $clockPoint))
        ->toThrow(\InvalidArgumentException::class, 'time_module_disabled');
});

it('weigert clock points-beheer zonder time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('time.clock-points.index'))
        ->assertForbidden();
});

it('laat clock point qr-print toe zonder time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('time.clock-points.qr', $clockPoint))
        ->assertOk();
});
