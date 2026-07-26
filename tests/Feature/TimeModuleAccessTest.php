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
        ->assertDontSee('href="'.route('time.presence.index').'"', false);
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

it('opent via de teams-knop altijd de default clock point QR (ook met time-module)', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openClockPointQr')
        ->assertRedirect(route('time.clock-points.qr', ClockPoint::query()->first()));

    expect(ClockPoint::query()->count())->toBe(1)
        ->and(ClockPoint::query()->first()?->name)->toBe(__('team.clock_point_qr.default_name'));
});

it('maakt een default clock point bij het openen van clock points-beheer', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    expect(ClockPoint::query()->count())->toBe(0);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Time\ClockPointsIndex::class)
        ->assertOk();

    expect(ClockPoint::query()->count())->toBe(1);
});

it('maakt een default clock point bij inschakelen van de time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    Tenancy::actAs($tenant->id);

    app(\App\Actions\Platform\ToggleTimeModuleAction::class)->handle($tenant, null);

    expect($tenant->fresh()->has_time_module)->toBeTrue()
        ->and(ClockPoint::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
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
