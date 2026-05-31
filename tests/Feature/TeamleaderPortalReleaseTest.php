<?php

use App\Livewire\Public\UnitPortal;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('laat een teamleader een geblokkeerde collega vrijgeven op het unit-portaal', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'default_internal_team_id' => $team->id,
        'is_active' => true,
        'qr_token' => 'unit-tl-release',
    ]);

    $teamleader = Worker::factory()->withIcon('crown')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_teamleader' => true,
        'first_name' => 'Team',
        'last_name' => 'Leader',
    ]);

    $locked = Worker::factory()->withIcon('heart')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Locked',
        'last_name' => 'Colleague',
        'field_icon_locked_at' => now(),
        'field_icon_failed_attempts' => 2,
    ]);

    WorkerVerification::markVerified($team, $teamleader);

    Livewire::test(UnitPortal::class, ['token' => 'unit-tl-release'])
        ->set('release_teamleader_icon_slug', 'crown')
        ->set('release_worker_id', $locked->id)
        ->call('releaseColleagueIcon')
        ->assertHasNoErrors();

    $locked->refresh();

    expect($locked->field_icon_slug)->toBeNull()
        ->and($locked->field_icon_locked_at)->toBeNull()
        ->and($locked->field_icon_failed_attempts)->toBe(0)
        ->and(WorkerDevice::where('worker_id', $locked->id)->count())->toBe(0);
});

it('weigert vrijgave wanneer het teamleader-icoon niet klopt', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'default_internal_team_id' => $team->id,
        'is_active' => true,
        'qr_token' => 'unit-tl-wrong',
    ]);

    $teamleader = Worker::factory()->withIcon('crown')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_teamleader' => true,
    ]);

    Worker::factory()->withIcon('heart')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Other',
        'last_name' => 'Worker',
    ]);

    WorkerVerification::markVerified($team, $teamleader);

    Livewire::test(UnitPortal::class, ['token' => 'unit-tl-wrong'])
        ->set('release_teamleader_icon_slug', 'heart')
        ->set('release_worker_id', Worker::where('internal_team_id', $team->id)->where('first_name', 'Other')->value('id'))
        ->call('releaseColleagueIcon')
        ->assertHasErrors('release_teamleader_icon_slug');
});

it('toont geen vrijgeven-knop wanneer er geen geblokkeerde collegas zijn', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'default_internal_team_id' => $team->id,
        'is_active' => true,
        'qr_token' => 'unit-tl-empty',
    ]);

    $teamleader = Worker::factory()->withIcon('crown')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_teamleader' => true,
    ]);

    WorkerVerification::markVerified($team, $teamleader);

    Livewire::test(UnitPortal::class, ['token' => 'unit-tl-empty'])
        ->assertSee(__('portal.teamleader.title'))
        ->assertSee(__('portal.teamleader.no_blocked_colleagues'))
        ->assertDontSee(__('portal.teamleader.open'));
});

it('vereist selectie van een geblokkeerde collega bij vrijgave', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'default_internal_team_id' => $team->id,
        'is_active' => true,
        'qr_token' => 'unit-tl-select',
    ]);

    $teamleader = Worker::factory()->withIcon('crown')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_teamleader' => true,
    ]);

    WorkerVerification::markVerified($team, $teamleader);

    Livewire::test(UnitPortal::class, ['token' => 'unit-tl-select'])
        ->set('release_teamleader_icon_slug', 'crown')
        ->call('releaseColleagueIcon')
        ->assertHasErrors('release_worker_id');
});
