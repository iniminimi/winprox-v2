<?php

use App\Actions\Locations\DeleteLocationImportBatchAction;
use App\Actions\Locations\ImportLocationsAction;
use App\Data\Locations\DeleteLocationImportBatchData;
use App\Data\Locations\ImportLocationsData;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Tenancy::forget());

it('imports locations from valid CSV', function () {
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
        ->and($result['count'])->toBe(2)
        ->and(Location::where('tenant_id', $tenant->id)->count())->toBe(2)
        ->and(Unit::where('tenant_id', $tenant->id)->where('is_site_unit', true)->count())->toBe(2)
        ->and(Location::where('name', 'Depot A')->value('import_batch_id'))->not->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'locations.import')->exists())->toBeTrue();
});

it('imports a location with address only when name is empty', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "street,postal_code,city\n";
    $csvContent .= "Kerkstraat,9000,Gent\n";

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
        ->and($result['count'])->toBe(1);

    $location = Location::where('tenant_id', $tenant->id)->first();
    expect($location?->name)->toBe('Kerkstraat')
        ->and($location?->city)->toBe('Gent');
});

it('fails when identity headers are missing', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "notes\n";
    $csvContent .= "Alleen notitie\n";

    $file = UploadedFile::fake()->createWithContent('locations.csv', $csvContent);

    $result = app(ImportLocationsAction::class)->handle(
        new ImportLocationsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
        ),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('fails when a row lacks name and full address', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "name,street,postal_code,city\n";
    $csvContent .= ",Kerkstraat,,Gent\n";

    $file = UploadedFile::fake()->createWithContent('locations.csv', $csvContent);

    $result = app(ImportLocationsAction::class)->handle(
        new ImportLocationsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
        ),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('ensures tenant isolation for location imports', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Tenancy::actAs($tenantA->id);
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

    $csvContent = "name,street,postal_code,city\n";
    $csvContent .= "Only A,Kerkstraat,9000,Gent\n";

    $file = UploadedFile::fake()->createWithContent('locations.csv', $csvContent);

    $result = app(ImportLocationsAction::class)->handle(
        new ImportLocationsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
        ),
        $tenantA->id,
        $userA->id,
    );

    expect($result['success'])->toBeTrue();
    expect(Location::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->where('name', 'Only A')->exists())->toBeTrue();
    expect(Location::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->where('name', 'Only A')->exists())->toBeFalse();
});

it('can undo a location import batch when locations have no content', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "name\n";
    $csvContent .= "Import Locatie 1\n";
    $csvContent .= "Import Locatie 2\n";

    $file = UploadedFile::fake()->createWithContent('locations.csv', $csvContent);

    $import = app(ImportLocationsAction::class)->handle(
        new ImportLocationsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
        ),
        $tenant->id,
        $user->id,
    );

    expect($import['success'])->toBeTrue();
    $batchId = $import['batch_id'];

    $result = app(DeleteLocationImportBatchAction::class)->handle(
        new DeleteLocationImportBatchData(importBatchId: $batchId),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeTrue()
        ->and($result['deleted_count'])->toBe(2)
        ->and(Location::where('tenant_id', $tenant->id)->where('import_batch_id', $batchId)->count())->toBe(0);
});

it('preserves imported locations that already have units when undoing', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "name\n";
    $csvContent .= "Keep Me\n";
    $csvContent .= "Delete Me\n";

    $file = UploadedFile::fake()->createWithContent('locations.csv', $csvContent);

    $import = app(ImportLocationsAction::class)->handle(
        new ImportLocationsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
        ),
        $tenant->id,
        $user->id,
    );

    $batchId = $import['batch_id'];
    $keep = Location::where('name', 'Keep Me')->firstOrFail();
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $keep->id,
    ]);

    $result = app(DeleteLocationImportBatchAction::class)->handle(
        new DeleteLocationImportBatchData(importBatchId: $batchId),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeTrue()
        ->and($result['deleted_count'])->toBe(1)
        ->and($result['preserved_count'])->toBe(1)
        ->and(Location::where('name', 'Keep Me')->exists())->toBeTrue()
        ->and(Location::where('name', 'Delete Me')->exists())->toBeFalse();
});
