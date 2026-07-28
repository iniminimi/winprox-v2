<?php

use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Qr\LocationQrPackStickerEntries;
use App\Support\Qr\UnitPortalUrl;
use App\Support\Tenancy;
use Illuminate\Support\Facades\URL;

afterEach(fn () => Tenancy::forget());

it('bouwt per unit een unieke meld-url met eigen qr_token', function () {
    URL::forceRootUrl('https://site.test');

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unitA = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Lift A']);
    $unitB = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Lift B']);

    $urlA = UnitPortalUrl::forUnit($unitA);
    $urlB = UnitPortalUrl::forUnit($unitB);

    expect($urlA)->not->toBe($urlB)
        ->and($urlA)->toContain('/melden/'.$unitA->qr_token)
        ->and($urlB)->toContain('/melden/'.$unitB->qr_token);

    $entries = LocationQrPackStickerEntries::forLocation($location->fresh());
    expect($entries)->toHaveCount(2)
        ->and($entries[0]->reportUrl)->not->toBe($entries[1]->reportUrl);
});

it('zet unitbeschrijving naast de naam op QR-stickerlabels', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Boormachine 001',
        'description' => 'Serienummer 34962',
    ]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lift A',
        'description' => null,
    ]);

    $entries = LocationQrPackStickerEntries::forLocation($location->fresh());

    expect($entries[0]->unitLabel)->toBe('Boormachine 001 - Serienummer 34962')
        ->and($entries[1]->unitLabel)->toBe('Lift A')
        ->and($entries[0]->locationUnitLabel)->toBe($location->name.' - Boormachine 001')
        ->and($entries[1]->locationUnitLabel)->toBe($location->name.' - Lift A');
});

it('geeft twee units met dezelfde naam elk een eigen qr_token', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $first = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Lift A']);
    $second = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Lift A']);

    expect($first->qr_token)->not->toBe($second->qr_token)
        ->and(strlen($first->qr_token))->toBeGreaterThan(20);
});

it('vult een ontbrekende qr_token aan voor een bestaande unit', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id]);
    $unit->forceFill(['qr_token' => ''])->saveQuietly();

    $token = UnitPortalUrl::ensureQrToken($unit->fresh());

    expect($token)->not->toBe('')
        ->and($unit->fresh()->qr_token)->toBe($token);
});
