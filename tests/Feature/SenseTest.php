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
        ->get(route('screens.pair', $unit))
        ->assertOk()
        ->assertSee('Babylokaal')
        ->assertSee('Winter')
        ->assertSee('Zomer');

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

it('start met de winterwaarden van een leefruimte', function () {
    Carbon::setTestNow('2026-11-24 12:00:00');
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id);
    $point = $created['point'];

    expect((float) $point->temperature_low)->toBe(20.0)
        ->and((float) $point->temperature_target)->toBe(21.0)
        ->and((float) $point->temperature_high)->toBe(24.0)
        ->and((float) $point->humidity_low)->toBe(40.0)
        ->and((float) $point->humidity_high)->toBe(60.0)
        ->and((int) $point->co2_high)->toBe(900)
        ->and((int) $point->voc_high)->toBe(150)
        ->and($point->season_applied)->toBe('winter');

    Carbon::setTestNow();
});

it('zet de zomerwaarden zonder de co2-grens te wijzigen', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id);

    $point = app(\App\Actions\Sense\ApplySensePresetAction::class)->handle($created['point'], 'summer', (int) $tenant->id);

    expect((float) $point->temperature_low)->toBe(22.0)
        ->and((float) $point->temperature_target)->toBe(24.0)
        ->and((float) $point->temperature_high)->toBe(26.0)
        ->and((float) $point->humidity_low)->toBe(30.0)
        ->and((float) $point->humidity_target)->toBe(50.0)
        ->and((float) $point->humidity_high)->toBe(70.0)
        ->and((int) $point->co2_high)->toBe(900);
});

it('vraagt pas aandacht als de voc-index een half uur hoog blijft', function () {
    Carbon::setTestNow('2026-11-24 12:00:00');
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id);
    $record = app(RecordSenseReadingAction::class);

    $record->handle($created['point'], new RecordSenseReadingData(21, 50, 700, 180, CarbonImmutable::parse('2026-11-24 12:00:00')));

    expect(\App\Support\Sense\SenseBands::attentions($created['point']->fresh()))->toBe([]);

    $record->handle($created['point']->fresh(), new RecordSenseReadingData(21, 50, 700, 180, CarbonImmutable::parse('2026-11-24 11:30:00')));
    $record->handle($created['point']->fresh(), new RecordSenseReadingData(21, 50, 700, 180, CarbonImmutable::parse('2026-11-24 12:00:00')));

    $held = \App\Support\Sense\SenseBands::attentions($created['point']->fresh());
    expect($held)->toHaveCount(1)
        ->and($held[0]['metric'])->toBe(\App\Enums\SenseMetric::Voc);

    $record->handle($created['point']->fresh(), new RecordSenseReadingData(21, 50, 700, 80, CarbonImmutable::parse('2026-11-24 11:45:00')));

    expect(\App\Support\Sense\SenseBands::attentions($created['point']->fresh()))->toBe([]);

    Carbon::setTestNow();
});

it('springt op 1 mei naar de zomer en op 1 oktober naar de winter', function () {
    Carbon::setTestNow('2026-04-30 12:00:00');
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id);
    $sync = app(\App\Actions\Sense\SyncSenseSeasonAction::class);

    expect($created['point']->season_applied)->toBe('winter');

    app(UpdateSenseBandsAction::class)->handle($created['point'], [
        'metric' => 'humidity',
        'low' => 42,
        'target' => 52,
        'high' => 62,
    ], (int) $tenant->id);

    Carbon::setTestNow('2026-04-30 18:00:00');
    expect($sync->handleTenant((int) $tenant->id))->toBe(0)
        ->and((float) $created['point']->fresh()->humidity_low)->toBe(42.0);

    Carbon::setTestNow('2026-05-01 00:10:00');
    expect($sync->handleTenant((int) $tenant->id))->toBe(1);
    $summer = $created['point']->fresh();
    expect((float) $summer->temperature_low)->toBe(22.0)
        ->and((float) $summer->temperature_target)->toBe(24.0)
        ->and((float) $summer->temperature_high)->toBe(26.0)
        ->and((float) $summer->humidity_low)->toBe(30.0)
        ->and((float) $summer->humidity_high)->toBe(70.0)
        ->and((int) $summer->co2_high)->toBe(900)
        ->and($summer->season_applied)->toBe('summer');

    Carbon::setTestNow('2026-09-30 12:00:00');
    expect($sync->handleTenant((int) $tenant->id))->toBe(0);

    Carbon::setTestNow('2026-10-01 00:10:00');
    expect($sync->handleTenant((int) $tenant->id))->toBe(1)
        ->and((float) $created['point']->fresh()->temperature_low)->toBe(20.0)
        ->and($created['point']->fresh()->season_applied)->toBe('winter');

    Carbon::setTestNow();
});

it('bewaart de echte meting en toont de schaalgrens op de kaart', function () {
    Carbon::setTestNow('2026-11-24 12:00:00');
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);
    [$unit] = senseRoom($tenant);
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id, (int) $admin->id);

    app(RecordSenseReadingAction::class)->handle($created['point'], new RecordSenseReadingData(
        temperature: 8.0,
        humidity: 20.0,
        co2: 2000,
        voc: 40,
        recordedAt: CarbonImmutable::now(),
    ));

    $point = $created['point']->fresh();
    expect((float) $point->last_temperature)->toBe(8.0)
        ->and((float) $point->last_humidity)->toBe(20.0)
        ->and((int) $point->last_co2)->toBe(2000);

    $this->actingAs($admin)
        ->get(route('screens.pair', $unit))
        ->assertOk()
        ->assertSee('12-')
        ->assertSee('30%-')
        ->assertSee('1.600+');

    Carbon::setTestNow();
});

it('verbergt sense voor een medewerker en voor checkmate', function () {
    $tenant = Tenant::factory()->create();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($employee)->get('/sense')->assertNotFound();

    $checkmate = Tenant::factory()->create(['checkmate_mode' => true]);
    $admin = User::factory()->admin()->create(['tenant_id' => $checkmate->id]);

    $this->actingAs($admin)->get('/sense')->assertNotFound();
});
