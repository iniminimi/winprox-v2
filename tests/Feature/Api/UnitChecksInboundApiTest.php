<?php

declare(strict_types=1);

use App\Enums\UnitCheckResult;
use App\Enums\UnitCheckSource;
use App\Events\Units\UnitCheckRecorded;
use App\Models\Category;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitCheck;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{tenant: Tenant, user: User, location: Location, unit: Unit, category: Category}
 */
function unitCheckInboundFixture(array $unitOverrides = []): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_checks' => true,
    ]);
    $unit = Unit::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'allow_unit_checks' => true,
        'external_id' => 'ROOM-42',
    ], $unitOverrides));

    return compact('tenant', 'user', 'location', 'unit', 'category');
}

it('records an inbound unit check by external unit id', function () {
    Event::fake([UnitCheckRecorded::class]);

    $fixture = unitCheckInboundFixture();
    $token = $fixture['user']->createToken('test', ['units:update'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/units/checks', [
        'external_unit_id' => 'ROOM-42',
        'external_id' => 'CHECK-1',
        'result' => 'ok',
        'checked_at' => '2026-08-01T10:15:00+02:00',
        'latitude' => 51.05,
        'longitude' => 3.72,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.result', 'ok')
        ->assertJsonPath('data.source', 'external')
        ->assertJsonPath('data.external_id', 'CHECK-1')
        ->assertJsonPath('data.unit_id', $fixture['unit']->id);

    Tenancy::actAs($fixture['tenant']->id);
    expect(UnitCheck::query()->count())->toBe(1)
        ->and(UnitCheck::query()->first()->source)->toBe(UnitCheckSource::External);

    Event::assertDispatched(UnitCheckRecorded::class);
});

it('returns existing check on external_id replay without re-dispatch', function () {
    Event::fake([UnitCheckRecorded::class]);

    $fixture = unitCheckInboundFixture();
    $token = $fixture['user']->createToken('test', ['units:update'])->plainTextToken;

    $payload = [
        'external_unit_id' => 'ROOM-42',
        'external_id' => 'CHECK-REPLAY',
        'result' => 'ok',
        'checked_at' => '2026-08-01T10:15:00+02:00',
    ];

    $this->withToken($token)->postJson('/api/v1/units/checks', $payload)->assertCreated();
    $replay = $this->withToken($token)->postJson('/api/v1/units/checks', $payload);

    $replay->assertOk()
        ->assertJsonPath('data.external_id', 'CHECK-REPLAY');

    Tenancy::actAs($fixture['tenant']->id);
    expect(UnitCheck::query()->count())->toBe(1);
    Event::assertDispatchedTimes(UnitCheckRecorded::class, 1);
});

it('rejects unknown external unit id', function () {
    $fixture = unitCheckInboundFixture();
    $token = $fixture['user']->createToken('test', ['units:update'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/units/checks', [
        'external_unit_id' => 'UNKNOWN',
        'result' => 'ok',
        'checked_at' => '2026-08-01T10:15:00+02:00',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['external_unit_id']);
});

it('isolates external unit ids per tenant', function () {
    $a = unitCheckInboundFixture();
    $b = unitCheckInboundFixture(['external_id' => 'ROOM-42']);

    $tokenB = $b['user']->createToken('test', ['units:update'])->plainTextToken;

    $this->withToken($tokenB)->postJson('/api/v1/units/checks', [
        'external_unit_id' => 'ROOM-42',
        'result' => 'not_ok',
        'checked_at' => '2026-08-01T11:00:00+02:00',
    ])->assertCreated()
        ->assertJsonPath('data.unit_id', $b['unit']->id)
        ->assertJsonPath('data.result', UnitCheckResult::NotOk->value);

    Tenancy::actAs($a['tenant']->id);
    expect(UnitCheck::query()->count())->toBe(0);

    Tenancy::actAs($b['tenant']->id);
    expect(UnitCheck::query()->count())->toBe(1);
});

it('rejects inbound check when unit checks are disabled', function () {
    $fixture = unitCheckInboundFixture(['allow_unit_checks' => false]);
    $token = $fixture['user']->createToken('test', ['units:update'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/units/checks', [
        'external_unit_id' => 'ROOM-42',
        'result' => 'ok',
        'checked_at' => '2026-08-01T10:15:00+02:00',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['external_unit_id']);
});

it('rejects inbound without units:update ability', function () {
    $fixture = unitCheckInboundFixture();
    $token = $fixture['user']->createToken('test', ['units:read'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/units/checks', [
        'external_unit_id' => 'ROOM-42',
        'result' => 'ok',
        'checked_at' => '2026-08-01T10:15:00+02:00',
    ])->assertForbidden();
});
