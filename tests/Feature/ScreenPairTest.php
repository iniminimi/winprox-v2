<?php

declare(strict_types=1);

use App\Livewire\Screens\Pair;
use App\Models\Location;
use App\Models\ScreenClaim;
use App\Models\SensePoint;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('koppelt een scherm aan een lokaal en geeft de sleutel alleen aan het toestel', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Babylokaal',
    ]);

    $this->actingAs($admin)
        ->get(route('locations.show', $location))
        ->assertOk()
        ->assertSee(route('screens.pair', $unit), false);

    $page = Livewire::actingAs($admin)
        ->test(Pair::class, ['unit' => $unit])
        ->assertSee('Babylokaal')
        ->call('issueCode');

    $code = $page->get('pairingCode');
    expect($code)->toHaveLength(8);
    $page->assertDontSee('wpsense_');

    $claim = $this->postJson(route('api.v1.screens.claim'), [
        'code' => substr($code, 0, 4).'-'.substr($code, 4),
        'device_hint' => 'D1S-babylokaal',
    ])->assertCreated()->json('data.claim_token');

    $page->call('confirmClaim', ScreenClaim::query()->first()->id)
        ->assertDontSee('wpsense_');

    $status = $this->getJson(route('api.v1.screens.claim-status', $claim))->assertOk();
    $key = $status->json('data.sense_key');
    expect($key)->toStartWith('wpsense_');

    $point = SensePoint::query()->where('unit_id', $unit->id)->first();
    expect($point)->not->toBeNull();
    expect($point->matchesToken($key))->toBeTrue();

    $this->withHeader('X-WinProx-Sense-Key', $key)
        ->postJson(route('api.v1.sense.readings.store'), [
            'temperature' => 21.5,
            'humidity' => 50,
            'co2' => 800,
            'voc' => 40,
            'recorded_at' => now()->toIso8601String(),
        ])
        ->assertCreated();
});

it('verbergt het scherm voor een medewerker en voor checkmate', function () {
    $tenant = Tenant::factory()->create();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);

    $this->actingAs($employee)->get(route('screens.pair', $unit))->assertForbidden();

    $checkmate = Tenant::factory()->create(['checkmate_mode' => true]);
    $admin = User::factory()->admin()->create(['tenant_id' => $checkmate->id]);
    $checkLocation = Location::factory()->create(['tenant_id' => $checkmate->id]);
    $checkUnit = Unit::factory()->create([
        'tenant_id' => $checkmate->id,
        'location_id' => $checkLocation->id,
    ]);

    $this->actingAs($admin)->get(route('screens.pair', $checkUnit))->assertNotFound();
});

it('bewaart de uren per scherm en laat de QR van een lokaal een uur meegaan', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $first = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Babylokaal',
    ]);
    $second = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Slaaplokaal',
    ]);

    Livewire::actingAs($admin)
        ->test(Pair::class, ['unit' => $first])
        ->assertSee('Screen settings')
        ->assertSee('once an hour')
        ->set('displayOnFrom', '06:00')
        ->set('displayOnUntil', '19:00')
        ->call('saveSchedule');

    $page = Livewire::actingAs($admin)
        ->test(Pair::class, ['unit' => $first])
        ->call('issueCode');

    $this->postJson(route('api.v1.screens.claim'), [
        'code' => $page->get('pairingCode'),
        'device_hint' => 'D1S-babylokaal',
    ])->assertCreated();

    $page->call('confirmClaim', ScreenClaim::query()->first()->id);

    $screen = $first->fresh()->screen;
    $other = $second->fresh()->screen;
    expect($screen->qr_window_seconds)->toBe(3600)
        ->and(substr((string) $screen->display_on_from, 0, 5))->toBe('06:00')
        ->and($other)->toBeNull();

    $phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
    $token = \App\Support\Screens\ScreenQr::tokenFor($screen);

    $this->withHeader('User-Agent', $phone)
        ->get(route('public.unit-portal', $token))
        ->assertOk()
        ->assertSee('Babylokaal');

    $stale = \App\Support\Screens\ScreenQr::tokenFor($screen, time() - 7200);
    $this->withHeader('User-Agent', $phone)
        ->get(route('public.unit-portal', $stale))
        ->assertNotFound();
});
