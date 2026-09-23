<?php

use App\Actions\Locations\CreateLocationAction;
use App\Actions\Locations\DeleteLocationAction;
use App\Actions\Locations\EnsureSiteUnitsForEmptyLocationsAction;
use App\Actions\Locations\ImportLocationsAction;
use App\Data\Locations\ImportLocationsData;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Locations\SiteUnitCatalog;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;

beforeEach(fn () => Tenancy::forget());

it('creates a site unit when creating a location', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $location = app(CreateLocationAction::class)->handle([
        'name' => 'Klant A',
        'country_code' => 'BE',
        'original_language' => 'nl',
    ], $tenant->id, $user->id);

    $unit = $location->units()->first();

    expect($unit)->not->toBeNull()
        ->and($unit->is_site_unit)->toBeTrue()
        ->and($unit->name)->toBe(SiteUnitCatalog::SOURCE_NAME)
        ->and($unit->allow_unit_checks)->toBeTrue()
        ->and($unit->roundStopDisplayName())->toBe('Klant A');
});

it('skips site unit when with_site_unit is false', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = app(CreateLocationAction::class)->handle([
        'name' => 'Zonder unit',
        'country_code' => 'BE',
        'with_site_unit' => false,
    ], $tenant->id);

    expect($location->units()->count())->toBe(0);
});

it('bulk-creates site units for empty locations', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Location::factory()->count(3)->for($tenant)->create();

    expect(Unit::query()->count())->toBe(0);

    $result = app(EnsureSiteUnitsForEmptyLocationsAction::class)->handle($tenant->id, $user->id);

    expect($result['created'])->toBe(3)
        ->and(Unit::query()->where('is_site_unit', true)->count())->toBe(3);
});

it('imports locations with a site unit each and can undo', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "name,street,house_number,postal_code,city,country_code\n";
    $csvContent .= "Depot A,Kerkstraat,1,9000,Gent,BE\n";
    $csvContent .= "Depot B,Wetstraat,16,1000,Brussel,BE\n";
    $file = UploadedFile::fake()->createWithContent('locations.csv', $csvContent);

    $result = app(ImportLocationsAction::class)->handle(
        new ImportLocationsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
        ),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeTrue()
        ->and(Location::where('tenant_id', $tenant->id)->count())->toBe(2)
        ->and(Unit::where('is_site_unit', true)->count())->toBe(2);

    $location = Location::where('name', 'Depot A')->firstOrFail();
    app(DeleteLocationAction::class)->handle($location, $user->id);

    expect(Location::where('name', 'Depot A')->exists())->toBeFalse()
        ->and(Unit::where('location_id', $location->id)->exists())->toBeFalse();
});
