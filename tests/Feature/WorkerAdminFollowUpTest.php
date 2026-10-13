<?php

use App\Actions\Team\CreateWorkerAction;
use App\Actions\Team\SetWorkerActiveAction;
use App\Actions\Team\UpdateWorkerAction;
use App\Livewire\Dashboard;
use App\Livewire\Pages\WorkersIndex;
use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

it('toont een uitvoerder van een teamleader op het dashboard tot beheer die afhandelt', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $leader = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_teamleader' => true,
        'first_name' => 'Lea',
        'last_name' => 'Leider',
    ]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $created = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Nieuw',
        'last_name' => 'Kind',
    ], null, $leader);
    $byAdmin = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Beheer',
        'last_name' => 'Zelf',
    ], (int) $admin->id);

    expect($created->needs_admin_follow_up)->toBeTrue()
        ->and($byAdmin->needs_admin_follow_up)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.kpi.worker_follow_up'), false)
        ->assertSeeHtml('wp-kpi--worker_follow_up');

    Livewire::actingAs($admin)
        ->test(WorkersIndex::class)
        ->set('followUpOnly', true)
        ->assertSee('Nieuw Kind')
        ->assertDontSee('Beheer Zelf')
        ->assertSee(__('team.workers_index.follow_up'), false);

    app(UpdateWorkerAction::class)->handle($created->fresh(), [
        'first_name' => 'Nieuw',
        'last_name' => 'Kind',
    ], (int) $admin->id);

    expect($created->fresh()->needs_admin_follow_up)->toBeFalse();

    $second = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Tweede',
        'last_name' => 'Kind',
    ], null, $leader);

    app(SetWorkerActiveAction::class)->handle($second, false, (int) $admin->id);

    expect($second->fresh()->needs_admin_follow_up)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertDontSeeHtml('wp-kpi--worker_follow_up');
});
