<?php

use App\Actions\Units\ImportUnitsAction;
use App\Data\Units\ImportUnitsData;
use App\Models\Category;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Tenancy::forget());

it('imports units into an existing location from valid CSV', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Depot Deinze',
    ]);

    $csvContent = "unit_name,description,category_name\n";
    $csvContent .= "Unit 1,Test description,Category A\n";
    $csvContent .= "Unit 2,,Category B\n";
    $csvContent .= "Unit 3,Another unit,\n";

    $file = UploadedFile::fake()->createWithContent('units.csv', $csvContent);

    $result = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            locationId: $location->id,
        ),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(3)
        ->and(Location::where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(Unit::where('tenant_id', $tenant->id)->where('location_id', $location->id)->count())->toBe(3)
        ->and(Category::where('tenant_id', $tenant->id)->count())->toBe(2)
        ->and(DB::table('audit_logs')->where('action', 'units.import')->exists())->toBeTrue();

    $withoutCategory = Unit::where('name', 'Unit 3')->first();
    expect($withoutCategory?->category_id)->toBeNull();
});

it('fails when headers do not match required format', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "wrong_header,another_wrong\n";
    $csvContent .= "Unit 1,x\n";

    $file = UploadedFile::fake()->createWithContent('units.csv', $csvContent);

    $result = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            locationId: $location->id,
        ),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('fails when required fields are missing in rows', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "unit_name,description,category_name\n";
    $csvContent .= "Unit 1,Test description,Category A\n";
    $csvContent .= ",Another description,\n";

    $file = UploadedFile::fake()->createWithContent('units.csv', $csvContent);

    $result = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            locationId: $location->id,
        ),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('ensures tenant isolation - units are only created for the importing tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Tenancy::actAs($tenantA->id);
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $location = Location::factory()->create(['tenant_id' => $tenantA->id]);

    $csvContent = "unit_name,description,category_name\n";
    $csvContent .= "Unit 1,Test description,Category A\n";

    $file = UploadedFile::fake()->createWithContent('units.csv', $csvContent);

    $result = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            locationId: $location->id,
        ),
        $tenantA->id,
        $userA->id,
    );

    expect($result['success'])->toBeTrue()
        ->and(Unit::where('tenant_id', $tenantA->id)->count())->toBe(1)
        ->and(Unit::where('tenant_id', $tenantB->id)->count())->toBe(0);
});

it('reuses existing categories and creates new ones when needed', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $existingCategory = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Existing Category']);

    $csvContent = "unit_name,description,category_name\n";
    $csvContent .= "Unit 1,Test description,Existing Category\n";
    $csvContent .= "Unit 2,Another description,New Category\n";

    $file = UploadedFile::fake()->createWithContent('units.csv', $csvContent);

    $result = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            locationId: $location->id,
        ),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeTrue();

    $unit1 = Unit::where('tenant_id', $tenant->id)->where('name', 'Unit 1')->first();
    expect($unit1->category_id)->toBe($existingCategory->id);

    $newCategory = Category::where('tenant_id', $tenant->id)->where('name', 'New Category')->first();
    expect($newCategory)->not->toBeNull();

    $unit2 = Unit::where('tenant_id', $tenant->id)->where('name', 'Unit 2')->first();
    expect($unit2->category_id)->toBe($newCategory->id);
});

it('rejects location columns for location-scoped import', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "location_name,unit_name\n";
    $csvContent .= "Somewhere,Unit 1\n";

    $file = UploadedFile::fake()->createWithContent('units.csv', $csvContent);

    $result = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            locationId: $location->id,
        ),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty()
        ->and(Unit::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('imports units from an Excel xlsx file', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $xlsxPath = tempnam(sys_get_temp_dir(), 'units_xlsx_').'.xlsx';
    \Tests\Support\MinimalXlsxFactory::write($xlsxPath, [
        ['unit_name', 'description', 'category_name'],
        ['Excel Unit 1', 'From spreadsheet', 'Earthmoving'],
        ['Excel Unit 2', '', ''],
    ]);

    $result = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(
            filePath: $xlsxPath,
            originalName: 'units.xlsx',
            locationId: $location->id,
        ),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(2)
        ->and(Unit::where('tenant_id', $tenant->id)->where('location_id', $location->id)->count())->toBe(2)
        ->and(Unit::where('name', 'Excel Unit 1')->first()?->category?->name)->toBe('Earthmoving')
        ->and(Unit::where('name', 'Excel Unit 2')->first()?->category_id)->toBeNull();

    @unlink($xlsxPath);
});
