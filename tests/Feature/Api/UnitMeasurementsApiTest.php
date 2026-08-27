<?php

declare(strict_types=1);

use App\Events\UnitMeasurements\UnitMeasurementRecorded;
use App\Models\Category;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitMeasureField;
use App\Models\UnitMeasurement;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{
 *     tenant: Tenant,
 *     user: User,
 *     location: Location,
 *     unit: Unit,
 *     field: UnitMeasureField
 * }
 */
function unitMeasurementsApiFixture(array $unitOverrides = []): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_measurements' => true,
    ]);
    $unit = Unit::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'allow_unit_measurements' => true,
    ], $unitOverrides));
    $field = UnitMeasureField::factory()->numeric('km')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Odometer',
    ]);
    $unit->measureFields()->sync([$field->id]);

    return compact('tenant', 'user', 'location', 'unit', 'field');
}

it('records a unit measurement via the API', function () {
    Event::fake([UnitMeasurementRecorded::class]);

    $fixture = unitMeasurementsApiFixture();
    $token = $fixture['user']->createToken('test', ['units:update'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/units/'.$fixture['unit']->id.'/measurements', [
        'unit_measure_field_id' => $fixture['field']->id,
        'recorded_at' => '2026-08-27T10:15:00+02:00',
        'value_numeric' => 125430.5,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.unit_id', $fixture['unit']->id)
        ->assertJsonPath('data.unit_measure_field_id', $fixture['field']->id)
        ->assertJsonPath('data.source', 'api')
        ->assertJsonPath('data.value_numeric', 125430.5);

    Tenancy::actAs($fixture['tenant']->id);
    expect(UnitMeasurement::query()->count())->toBe(1);
    Event::assertDispatched(UnitMeasurementRecorded::class);
});

it('rejects API measurement without units:update ability', function () {
    $fixture = unitMeasurementsApiFixture();
    $token = $fixture['user']->createToken('test', ['units:read'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/units/'.$fixture['unit']->id.'/measurements', [
        'unit_measure_field_id' => $fixture['field']->id,
        'recorded_at' => now()->toIso8601String(),
        'value_numeric' => 1,
    ])->assertForbidden();
});

it('rejects API measurement when unit measurements are disabled', function () {
    $fixture = unitMeasurementsApiFixture(['allow_unit_measurements' => false]);
    $token = $fixture['user']->createToken('test', ['units:update'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/units/'.$fixture['unit']->id.'/measurements', [
        'unit_measure_field_id' => $fixture['field']->id,
        'recorded_at' => now()->toIso8601String(),
        'value_numeric' => 1,
    ])->assertStatus(422);
});
