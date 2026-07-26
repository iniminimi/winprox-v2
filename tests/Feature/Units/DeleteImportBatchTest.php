<?php

use App\Actions\Units\DeleteImportBatchAction;
use App\Actions\Units\ImportUnitsAction;
use App\Data\Units\DeleteImportBatchData;
use App\Data\Units\ImportUnitsData;
use App\Models\Category;
use App\Models\Issue;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

beforeEach(fn () => Tenancy::forget());

it('deletes units without issues or tasks', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    $batchId = 'test-batch-123';

    // Create units in the batch without issues or tasks
    Unit::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'import_batch_id' => $batchId,
    ]);

    $dto = new DeleteImportBatchData(importBatchId: $batchId);
    $action = app(DeleteImportBatchAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeTrue();
    expect($result['deleted_count'])->toBe(3);
    expect($result['preserved_count'])->toBe(0);
    expect($result['total_count'])->toBe(3);

    // Verify all units were deleted
    expect(Unit::where('tenant_id', $tenant->id)->where('import_batch_id', $batchId)->count())->toBe(0);

    // Verify audit log was written
    expect(DB::table('audit_logs')->where('action', 'units.delete_import_batch')->exists())->toBeTrue();
});

it('preserves units with issues', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    $batchId = 'test-batch-456';

    // Create unit without issue (should be deleted)
    $unitWithoutIssue = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'import_batch_id' => $batchId,
    ]);

    // Create unit with issue (should be preserved)
    $unitWithIssue = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'import_batch_id' => $batchId,
    ]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unitWithIssue->id,
    ]);

    $dto = new DeleteImportBatchData(importBatchId: $batchId);
    $action = app(DeleteImportBatchAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeTrue();
    expect($result['deleted_count'])->toBe(1);
    expect($result['preserved_count'])->toBe(1);
    expect($result['total_count'])->toBe(2);

    // Verify unit without issue was deleted
    expect(Unit::where('id', $unitWithoutIssue->id)->exists())->toBeFalse();

    // Verify unit with issue was preserved
    expect(Unit::where('id', $unitWithIssue->id)->exists())->toBeTrue();
});

it('preserves units with tasks', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    $batchId = 'test-batch-789';

    // Create unit without task (should be deleted)
    $unitWithoutTask = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'import_batch_id' => $batchId,
    ]);

    // Create unit with task (should be preserved)
    $unitWithTask = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'import_batch_id' => $batchId,
    ]);

    // Create issue for the unit, then task for the issue
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unitWithTask->id,
    ]);

    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
    ]);

    $dto = new DeleteImportBatchData(importBatchId: $batchId);
    $action = app(DeleteImportBatchAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeTrue();
    expect($result['deleted_count'])->toBe(1);
    expect($result['preserved_count'])->toBe(1);
    expect($result['total_count'])->toBe(2);

    // Verify unit without task was deleted
    expect(Unit::where('id', $unitWithoutTask->id)->exists())->toBeFalse();

    // Verify unit with task was preserved
    expect(Unit::where('id', $unitWithTask->id)->exists())->toBeTrue();
});

it('ensures tenant isolation - cannot delete units from other tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Tenancy::actAs($tenantA->id);
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

    $locationA = Location::factory()->create(['tenant_id' => $tenantA->id]);
    $locationB = Location::factory()->create(['tenant_id' => $tenantB->id]);
    $categoryA = Category::factory()->create(['tenant_id' => $tenantA->id]);
    $categoryB = Category::factory()->create(['tenant_id' => $tenantB->id]);

    $batchId = 'test-batch-isolation';

    // Create units for tenant A
    Unit::withoutGlobalScopes()->create([
        'tenant_id' => $tenantA->id,
        'location_id' => $locationA->id,
        'category_id' => $categoryA->id,
        'import_batch_id' => $batchId,
        'name' => 'Unit A1',
        'is_active' => true,
    ]);
    Unit::withoutGlobalScopes()->create([
        'tenant_id' => $tenantA->id,
        'location_id' => $locationA->id,
        'category_id' => $categoryA->id,
        'import_batch_id' => $batchId,
        'name' => 'Unit A2',
        'is_active' => true,
    ]);

    // Create units for tenant B with same batch ID
    Unit::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'location_id' => $locationB->id,
        'category_id' => $categoryB->id,
        'import_batch_id' => $batchId,
        'name' => 'Unit B1',
        'is_active' => true,
    ]);
    Unit::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'location_id' => $locationB->id,
        'category_id' => $categoryB->id,
        'import_batch_id' => $batchId,
        'name' => 'Unit B2',
        'is_active' => true,
    ]);

    $dto = new DeleteImportBatchData(importBatchId: $batchId);
    $action = app(DeleteImportBatchAction::class);
    $result = $action->handle($dto, $tenantA->id, $userA->id);

    expect($result['success'])->toBeTrue();
    expect($result['deleted_count'])->toBe(2);
    expect($result['preserved_count'])->toBe(0);

    // Verify tenant A units were deleted
    expect(Unit::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->where('import_batch_id', $batchId)->count())->toBe(0);

    // Verify tenant B units still exist
    expect(Unit::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->where('import_batch_id', $batchId)->count())->toBe(2);
});

it('returns error when batch not found', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $dto = new DeleteImportBatchData(importBatchId: 'non-existent-batch');
    $action = app(DeleteImportBatchAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeFalse();
    expect($result['errors'])->not->toBeEmpty();
});

it('preserves units with both issues and tasks', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    $batchId = 'test-batch-both';

    // Create unit with both issue and task (should be preserved)
    $unitWithBoth = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'import_batch_id' => $batchId,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unitWithBoth->id,
    ]);

    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
    ]);

    // Create unit without anything (should be deleted)
    $unitWithout = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'import_batch_id' => $batchId,
    ]);

    $dto = new DeleteImportBatchData(importBatchId: $batchId);
    $action = app(DeleteImportBatchAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeTrue();
    expect($result['deleted_count'])->toBe(1);
    expect($result['preserved_count'])->toBe(1);

    // Verify unit with both was preserved
    expect(Unit::where('id', $unitWithBoth->id)->exists())->toBeTrue();

    // Verify unit without was deleted
    expect(Unit::where('id', $unitWithout->id)->exists())->toBeFalse();
});

it('deletes empty categories created during import on batch undo without deleting the location', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Depot A']);

    $csvPath = tempnam(sys_get_temp_dir(), 'units_undo_test_').'.csv';
    $handle = fopen($csvPath, 'w');
    fputcsv($handle, ['unit_name', 'description', 'category_name']);
    fputcsv($handle, ['Unit A1', 'Test', 'Grondverzet']);
    fputcsv($handle, ['Unit A2', 'Test', 'Heftrucks']);
    fclose($handle);

    $importResult = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(filePath: $csvPath, originalName: 'units.csv', locationId: $location->id),
        $tenant->id,
        $user->id,
    );

    expect($importResult['success'])->toBeTrue()
        ->and(Category::where('tenant_id', $tenant->id)->count())->toBe(2);

    $deleteResult = app(DeleteImportBatchAction::class)->handle(
        new DeleteImportBatchData(importBatchId: $importResult['batch_id']),
        $tenant->id,
        $user->id,
    );

    expect($deleteResult['success'])->toBeTrue()
        ->and($deleteResult['deleted_count'])->toBe(2)
        ->and($deleteResult['deleted_location_count'])->toBe(0)
        ->and($deleteResult['deleted_category_count'])->toBe(2)
        ->and(Unit::where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(Location::where('tenant_id', $tenant->id)->whereKey($location->id)->exists())->toBeTrue()
        ->and(Category::where('tenant_id', $tenant->id)->count())->toBe(0);

    unlink($csvPath);
});

it('keeps existing category with other units on batch undo', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $existingLocation = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bestaand Depot']);
    $existingCategory = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Grondverzet']);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $existingLocation->id,
        'category_id' => $existingCategory->id,
        'import_batch_id' => null,
    ]);

    $csvPath = tempnam(sys_get_temp_dir(), 'units_reuse_test_').'.csv';
    $handle = fopen($csvPath, 'w');
    fputcsv($handle, ['unit_name', 'category_name']);
    fputcsv($handle, ['Import Unit', 'Grondverzet']);
    fclose($handle);

    $importResult = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(filePath: $csvPath, originalName: 'reuse.csv', locationId: $existingLocation->id),
        $tenant->id,
        $user->id,
    );

    $deleteResult = app(DeleteImportBatchAction::class)->handle(
        new DeleteImportBatchData(importBatchId: $importResult['batch_id']),
        $tenant->id,
        $user->id,
    );

    expect($deleteResult['success'])->toBeTrue()
        ->and($deleteResult['deleted_count'])->toBe(1)
        ->and($deleteResult['deleted_location_count'])->toBe(0)
        ->and($deleteResult['deleted_category_count'])->toBe(0)
        ->and(Location::where('tenant_id', $tenant->id)->where('name', 'Bestaand Depot')->exists())->toBeTrue()
        ->and(Category::where('tenant_id', $tenant->id)->where('name', 'Grondverzet')->exists())->toBeTrue()
        ->and(Unit::where('tenant_id', $tenant->id)->count())->toBe(1);

    unlink($csvPath);
});

it('refreshes the location detail after undoing a units CSV import', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Import Depot']);

    $csvPath = tempnam(sys_get_temp_dir(), 'units_show_test_').'.csv';
    $handle = fopen($csvPath, 'w');
    fputcsv($handle, ['unit_name', 'category_name']);
    fputcsv($handle, ['Unit 1', 'Grondverzet']);
    fputcsv($handle, ['Unit 2', 'Heftrucks']);
    fclose($handle);

    $importResult = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(filePath: $csvPath, originalName: 'units.csv', locationId: $location->id),
        $tenant->id,
        $user->id,
    );

    expect($importResult['success'])->toBeTrue();

    Livewire::actingAs($user)
        ->test(\App\Livewire\Locations\Show::class, ['location' => $location])
        ->assertSee('Unit 1')
        ->call('deleteImportBatch', $importResult['batch_id'])
        ->assertDontSee('Unit 1')
        ->assertSet('unitsImportNoticeType', 'success');

    expect(Unit::where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(Location::where('tenant_id', $tenant->id)->whereKey($location->id)->exists())->toBeTrue();

    unlink($csvPath);
});
