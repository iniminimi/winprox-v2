<?php

declare(strict_types=1);

use App\Actions\Screens\EnableSenseOnScreenAction;
use App\Actions\Screens\EnsureClockScreenAction;
use App\Actions\Sense\CreateSensePointAction;
use App\Actions\Sense\RecordSenseReadingAction;
use App\Data\Sense\RecordSenseReadingData;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

afterEach(fn () => Tenancy::forget());

function senseReadingsRoom(Tenant $tenant, string $name = 'Babylokaal'): Unit
{
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    return Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => $name,
    ]);
}

it('toont de metingen van een scherm en downloadt ze', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);
    $unit = senseReadingsRoom($tenant, 'Babylokaal');
    $created = app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id, (int) $admin->id);

    app(RecordSenseReadingAction::class)->handle($created['point'], new RecordSenseReadingData(
        temperature: 21.5,
        humidity: 40,
        co2: 980,
        voc: 80,
        recordedAt: CarbonImmutable::parse('2026-11-28 12:09:00', config('app.timezone')),
    ));

    $this->actingAs($admin)
        ->get(route('screens.pair', $unit))
        ->assertOk()
        ->assertSee('Metingen')
        ->assertSee(route('screens.sense', $unit), false);

    $this->actingAs($admin)
        ->get(route('screens.sense', $unit))
        ->assertOk()
        ->assertSee('Metingen — Babylokaal')
        ->assertSee('Locatie')
        ->assertSee('28-11-2026 12:09 : Temperatuur 21,5 °C · Luchtvochtigheid 40 % · CO₂ 980 ppm · VOC-index 80', false);

    $export = $this->actingAs($admin)->get(route('screens.sense.export', [
        'unit' => $unit,
        'from' => '2026-11-28',
        'until' => '2026-11-28',
    ]));
    $export->assertOk();
    $export->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $binary = $export->streamedContent();
    expect($binary)->toStartWith('PK');
    $path = tempnam(sys_get_temp_dir(), 'wp-xlsx-test-');
    file_put_contents($path, $binary);
    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);
    expect($sheet)->toContain('21.5')->toContain('980');

    $this->actingAs($admin)
        ->get(route('screens.sense.print', [
            'unit' => $unit,
            'from' => '2026-11-28',
            'until' => '2026-11-28',
        ]))
        ->assertOk()
        ->assertSee('28-11-2026 12:09 : Temperatuur 21,5 °C · Luchtvochtigheid 40 % · CO₂ 980 ppm · VOC-index 80', false);
});

it('toont de metingen van een clock point', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Duinhummeltjes',
    ]);
    $screen = app(EnsureClockScreenAction::class)->handle($clockPoint);
    $created = app(EnableSenseOnScreenAction::class)->handle($screen, (int) $tenant->id, (int) $admin->id);

    app(RecordSenseReadingAction::class)->handle($created['point'], new RecordSenseReadingData(
        temperature: 18,
        humidity: 55,
        co2: 1100,
        voc: 40,
        recordedAt: CarbonImmutable::now(),
    ));

    $this->actingAs($admin)
        ->get(route('time.clock-displays.sense', $clockPoint))
        ->assertOk()
        ->assertSee('Metingen — Duinhummeltjes')
        ->assertSee('18 °C');
});

it('weigert een medewerker en een scherm zonder sense', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    seedTenantPastOnboarding($tenant);
    $unit = senseReadingsRoom($tenant);

    $this->actingAs($employee)->get(route('screens.sense', $unit))->assertForbidden();
    $this->actingAs($admin)->get(route('screens.sense', $unit))->assertNotFound();

    $checkmate = Tenant::factory()->create(['checkmate_mode' => true]);
    $checkAdmin = User::factory()->admin()->create(['tenant_id' => $checkmate->id]);
    seedTenantPastOnboarding($checkmate);
    $checkUnit = senseReadingsRoom($checkmate);

    $this->actingAs($checkAdmin)->get(route('screens.sense', $checkUnit))->assertNotFound();
});

it('vraagt een kortere periode als het bereik te lang is', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);
    $unit = senseReadingsRoom($tenant);
    app(CreateSensePointAction::class)->handle((int) $unit->id, (int) $tenant->id, (int) $admin->id);

    $this->actingAs($admin)
        ->get(route('screens.sense', [
            'unit' => $unit,
            'from' => '2026-01-01',
            'until' => '2026-03-01',
        ]))
        ->assertOk()
        ->assertSee('Kies hoogstens 31 dagen.');
});
