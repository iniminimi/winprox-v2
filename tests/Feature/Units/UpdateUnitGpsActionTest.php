<?php

declare(strict_types=1);

use App\Actions\Units\UpdateUnitGpsAction;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;

afterEach(fn () => Tenancy::forget());

it('updates unit gps coordinates', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'latitude' => null,
        'longitude' => null,
    ]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $action = app(UpdateUnitGpsAction::class);
    $result = $action->handle(
        unit: $unit,
        latitude: 51.12345678,
        longitude: 4.56789012,
        tenantId: $tenant->id,
        actorUserId: $user->id
    );

    expect($result)->toBeInstanceOf(Unit::class)
        ->and($result->id)->toBe($unit->id)
        ->and($result->latitude)->toBe(51.12345678)
        ->and($result->longitude)->toBe(4.56789012);

    $unit->refresh();
    expect($unit->latitude)->toBe(51.12345678)
        ->and($unit->longitude)->toBe(4.56789012)
        ->and($unit->hasGps())->toBeTrue();
});

it('returns correct google maps url', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'latitude' => 51.12345678,
        'longitude' => 4.56789012,
    ]);

    expect($unit->googleMapsUrl())->toBe('https://www.google.com/maps/search/?api=1&query=51.12345678,4.56789012');
});

it('returns null for google maps url when no gps', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'latitude' => null,
        'longitude' => null,
    ]);

    expect($unit->hasGps())->toBeFalse()
        ->and($unit->googleMapsUrl())->toBeNull();
});

it('creates audit log entry when updating gps', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'latitude' => null,
        'longitude' => null,
    ]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $action = app(UpdateUnitGpsAction::class);
    $action->handle(
        unit: $unit,
        latitude: 51.98765432,
        longitude: 3.12345678,
        tenantId: $tenant->id,
        actorUserId: $user->id
    );

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'action' => 'unit.gps_updated',
        'model_type' => Unit::class,
        'model_id' => $unit->id,
    ]);
});

it('validates gps coordinates in request', function () {
    $request = new \App\Http\Requests\Units\UpdateUnitGpsRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKeys(['latitude', 'longitude'])
        ->and($rules['latitude'])->toContain('between:-90,90')
        ->and($rules['longitude'])->toContain('between:-180,180');
});

it('can overwrite existing gps coordinates', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'latitude' => 50.0,
        'longitude' => 3.0,
    ]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $action = app(UpdateUnitGpsAction::class);
    $result = $action->handle(
        unit: $unit,
        latitude: 51.98765432,
        longitude: 4.56789012,
        tenantId: $tenant->id,
        actorUserId: $user->id
    );

    expect($result->latitude)->toBe(51.98765432)
        ->and($result->longitude)->toBe(4.56789012);

    $unit->refresh();
    expect($unit->latitude)->toBe(51.98765432)
        ->and($unit->longitude)->toBe(4.56789012)
        ->and($unit->hasGps())->toBeTrue();
});

it('validates static rules match instance rules', function () {
    $instanceRules = (new \App\Http\Requests\Units\UpdateUnitGpsRequest())->rules();
    $staticRules = \App\Http\Requests\Units\UpdateUnitGpsRequest::staticRules();

    expect($staticRules)->toBe($instanceRules);
});
