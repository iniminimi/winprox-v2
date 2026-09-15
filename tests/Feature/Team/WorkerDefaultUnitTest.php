<?php

declare(strict_types=1);

use App\Actions\Team\CreateWorkerAction;
use App\Actions\Team\UpdateWorkerAction;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;

afterEach(fn () => Tenancy::forget());

it('slaat standaardgroep op bij create en update worker', function () {
    [$tenant, $admin] = tenantWithAdmin();
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'roster_code' => 'GR1',
        'is_active' => true,
    ]);

    $worker = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Kim',
        'last_name' => 'Rooster',
        'location_ids' => [$location->id],
        'default_unit_id' => $unit->id,
    ], (int) $admin->id);

    expect($worker->default_unit_id)->toBe((int) $unit->id)
        ->and($worker->canClockAt((int) $location->id))->toBeTrue();

    $other = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'roster_code' => 'GR2',
        'is_active' => true,
    ]);

    $updated = app(UpdateWorkerAction::class)->handle($worker, [
        'first_name' => 'Kim',
        'last_name' => 'Rooster',
        'location_ids' => [$location->id],
        'default_unit_id' => $other->id,
    ], (int) $admin->id);

    expect($updated->default_unit_id)->toBe((int) $other->id)
        ->and($updated->canClockAt((int) $location->id))->toBeTrue();
});

it('weigert standaardgroep buiten toegewezen locatie', function () {
    [$tenant, $admin] = tenantWithAdmin();
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $home = Location::factory()->create(['tenant_id' => $tenant->id]);
    $away = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unitAway = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $away->id,
        'roster_code' => 'AW1',
        'is_active' => true,
    ]);

    expect(fn () => app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Out',
        'last_name' => 'OfScope',
        'location_ids' => [$home->id],
        'default_unit_id' => $unitAway->id,
    ], (int) $admin->id))->toThrow(ValidationException::class);
});

it('weigert unit zonder roostercode als standaardgroep', function () {
    [$tenant, $admin] = tenantWithAdmin();
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'roster_code' => null,
        'is_active' => true,
    ]);

    expect(fn () => app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'No',
        'last_name' => 'Code',
        'location_ids' => [$location->id],
        'default_unit_id' => $unit->id,
    ], (int) $admin->id))->toThrow(ValidationException::class);
});

it('laat standaardgroep op elke locatie toe voor floater-team', function () {
    [$tenant, $admin] = tenantWithAdmin();
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'clocks_all_locations' => true,
    ]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'roster_code' => 'FL1',
        'is_active' => true,
    ]);

    $worker = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Float',
        'last_name' => 'Er',
        'location_ids' => [],
        'default_unit_id' => $unit->id,
    ], (int) $admin->id);

    expect($worker->default_unit_id)->toBe((int) $unit->id)
        ->and($worker->canClockAt((int) $location->id))->toBeTrue();
});
