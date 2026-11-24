<?php

declare(strict_types=1);

use App\Actions\Sense\CreateSensePointAction;
use App\Actions\Sense\RecordSenseReadingAction;
use App\Actions\Sense\UpdateSenseBandsAction;
use App\Data\Sense\RecordSenseReadingData;
use App\Models\Issue;
use App\Models\Location;
use App\Models\SensePoint;
use App\Models\SenseReading;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

afterEach(fn () => Tenancy::forget());

function senseRoom(Tenant $tenant, string $name = 'Babylokaal'): array
{
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => $name,
    ]);

    return [$unit, $location];
}

it('zet sense onder plaatsen en toont aandacht zonder melding', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);

    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id, (int) $admin->id);

    app(RecordSenseReadingAction::class)->handle($created['point'], new RecordSenseReadingData(
        temperature: 21.5,
        humidity: 52,
        co2: 1450,
        voc: 80,
        recordedAt: CarbonImmutable::now(),
    ));

    expect(Issue::query()->count())->toBe(0);

    $this->actingAs($admin)
        ->get(route('sense.index'))
        ->assertOk()
        ->assertSee('Babylokaal')
        ->assertSee('Sense');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Babylokaal')
        ->assertSee('1.450 ppm');
});

it('slaat de streefwaarde niet als alarm', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id);

    app(RecordSenseReadingAction::class)->handle($created['point'], new RecordSenseReadingData(
        temperature: 23.5,
        humidity: 50,
        co2: 800,
        voc: 40,
        recordedAt: CarbonImmutable::now(),
    ));

    $point = $created['point']->fresh();
    expect(\App\Support\Sense\SenseBands::attentions($point))->toBe([]);
});

it('weigert een streefwaarde buiten onder en boven', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id);

    expect(fn () => app(UpdateSenseBandsAction::class)->handle($created['point'], [
        'metric' => 'temperature',
        'low' => 20,
        'target' => 16,
        'high' => 24,
    ], (int) $tenant->id))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('neemt een meting één keer op en maakt geen melding', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id);
    $when = CarbonImmutable::parse('2026-11-24T11:42:00+01:00');
    $data = new RecordSenseReadingData(21.5, 52, 800, 40, $when);

    $first = app(RecordSenseReadingAction::class)->handle($created['point'], $data);
    $second = app(RecordSenseReadingAction::class)->handle($created['point']->fresh(), $data);

    expect($first['created'])->toBeTrue()
        ->and($second['created'])->toBeFalse()
        ->and(SenseReading::query()->count())->toBe(1)
        ->and(Issue::query()->count())->toBe(0);
});

it('aanvaardt een meting met de lokaalsleutel', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id);
    Tenancy::forget();

    $this->withHeaders(['X-WinProx-Sense-Key' => $created['plain_token']])
        ->postJson('/api/v1/sense/readings', [
            'temperature' => 15,
            'humidity' => 52,
            'co2' => 800,
            'voc' => 40,
            'recorded_at' => '2026-11-24T11:42:00+01:00',
        ])
        ->assertCreated()
        ->assertJsonPath('data.attention', true);

    expect(Issue::query()->count())->toBe(0)
        ->and(SensePoint::query()->find($created['point']->id)->last_temperature)->toEqual(15.0);
});

it('toont een stilgevallen scherm als aandacht', function () {
    Carbon::setTestNow('2026-11-24 12:00:00');
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id);

    app(RecordSenseReadingAction::class)->handle($created['point'], new RecordSenseReadingData(
        temperature: 21,
        humidity: 50,
        co2: 700,
        voc: 30,
        recordedAt: CarbonImmutable::parse('2026-11-24 11:00:00'),
    ));

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('geen meting sinds');

    Carbon::setTestNow();
});

it('verbergt sense voor een medewerker en voor checkmate', function () {
    $tenant = Tenant::factory()->create();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($employee)->get(route('sense.index'))->assertForbidden();

    $checkmate = Tenant::factory()->create(['checkmate_mode' => true]);
    $admin = User::factory()->admin()->create(['tenant_id' => $checkmate->id]);

    $this->actingAs($admin)->get(route('sense.index'))->assertNotFound();
});
