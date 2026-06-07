<?php

use App\Actions\Workers\DeleteWorkerImportBatchAction;
use App\Actions\Workers\ImportWorkersAction;
use App\Data\Workers\DeleteWorkerImportBatchData;
use App\Data\Workers\ImportWorkersData;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Tenancy::forget());

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeCsvFile(array $rows, string $separator = ','): string
{
    $path = tempnam(sys_get_temp_dir(), 'workers_test_') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, $separator);
    }
    fclose($handle);

    return $path;
}

// ---------------------------------------------------------------------------
// Scenario 1: Succesvolle import met alle velden
// ---------------------------------------------------------------------------

it('imports workers with all fields successfully', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csvPath = makeCsvFile([
        ['team_name', 'first_name', 'last_name', 'email', 'phone', 'external_id'],
        ['Onderhoud', 'Jan', 'Janssen', 'jan@bedrijf.be', '+32470123456', 'EMP-001'],
        ['Schoonmaak', 'Piet', 'Pieters', 'piet@bedrijf.be', '+32470654321', 'EMP-002'],
    ]);

    $dto = new ImportWorkersData(filePath: $csvPath, originalName: 'workers.csv');
    $action = app(ImportWorkersAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeTrue();
    expect($result['count'])->toBe(2);
    expect($result['batch_id'])->toBeString()->not->toBeEmpty();

    $workers = Worker::where('tenant_id', $tenant->id)->where('import_batch_id', $result['batch_id'])->get();
    expect($workers)->toHaveCount(2);

    $jan = $workers->firstWhere('first_name', 'Jan');
    expect($jan->last_name)->toBe('Janssen');
    expect($jan->email)->toBe('jan@bedrijf.be');
    expect($jan->phone)->toBe('+32470123456');
    expect($jan->external_id)->toBe('EMP-001');
    expect($jan->is_active)->toBeTrue();

    // Teams worden aangemaakt
    expect(InternalTeam::where('tenant_id', $tenant->id)->where('name', 'Onderhoud')->exists())->toBeTrue();
    expect(InternalTeam::where('tenant_id', $tenant->id)->where('name', 'Schoonmaak')->exists())->toBeTrue();

    // Audit log
    expect(DB::table('audit_logs')->where('action', 'workers.import')->where('tenant_id', $tenant->id)->exists())->toBeTrue();

    unlink($csvPath);
});

// ---------------------------------------------------------------------------
// Scenario 2: Succesvolle import zonder optionele velden
// ---------------------------------------------------------------------------

it('imports workers with only required fields (no optional fields)', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csvPath = makeCsvFile([
        ['team_name', 'first_name', 'last_name'],
        ['Technisch', 'Marie', 'Maes'],
    ]);

    $dto = new ImportWorkersData(filePath: $csvPath, originalName: 'workers_minimal.csv');
    $action = app(ImportWorkersAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeTrue();
    expect($result['count'])->toBe(1);

    $worker = Worker::where('tenant_id', $tenant->id)->where('first_name', 'Marie')->first();
    expect($worker)->not->toBeNull();
    expect($worker->email)->toBeNull();
    expect($worker->phone)->toBeNull();
    expect($worker->external_id)->toBeNull();
    expect($worker->import_batch_id)->toBe($result['batch_id']);

    unlink($csvPath);
});

// ---------------------------------------------------------------------------
// Scenario 3: Bestaand team wordt hergebruikt (firstOrCreate)
// ---------------------------------------------------------------------------

it('reuses existing team when team_name already exists for tenant', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $existingTeam = InternalTeam::create(['tenant_id' => $tenant->id, 'name' => 'Bestaand Team', 'is_active' => true]);

    $csvPath = makeCsvFile([
        ['team_name', 'first_name', 'last_name'],
        ['Bestaand Team', 'Anna', 'Vermeersch'],
    ]);

    $dto = new ImportWorkersData(filePath: $csvPath, originalName: 'reuse.csv');
    $action = app(ImportWorkersAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeTrue();

    $worker = Worker::where('tenant_id', $tenant->id)->where('first_name', 'Anna')->first();
    expect($worker->internal_team_id)->toBe($existingTeam->id);

    // Geen dubbel team aangemaakt
    expect(InternalTeam::where('tenant_id', $tenant->id)->where('name', 'Bestaand Team')->count())->toBe(1);

    unlink($csvPath);
});

// ---------------------------------------------------------------------------
// Scenario 4: Undo — workers zonder devices worden verwijderd
// ---------------------------------------------------------------------------

it('deletes workers without devices on batch undo', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $batchId = 'batch-no-devices';

    Worker::factory()->count(3)->create([
        'tenant_id'       => $tenant->id,
        'internal_team_id' => $team->id,
        'import_batch_id' => $batchId,
    ]);

    $dto = new DeleteWorkerImportBatchData(importBatchId: $batchId);
    $action = app(DeleteWorkerImportBatchAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeTrue();
    expect($result['deleted_count'])->toBe(3);
    expect($result['preserved_count'])->toBe(0);
    expect($result['total_count'])->toBe(3);

    expect(Worker::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('import_batch_id', $batchId)->count())->toBe(0);

    // Audit log
    expect(DB::table('audit_logs')->where('action', 'workers.delete_import_batch')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Scenario 5: Undo — workers MET devices worden bewaard (veiligheidsgrendel)
// ---------------------------------------------------------------------------

it('preserves workers with active devices on batch undo', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $batchId = 'batch-mixed-devices';

    // Worker zonder device — mag verwijderd worden
    $workerSafe = Worker::factory()->create([
        'tenant_id'       => $tenant->id,
        'internal_team_id' => $team->id,
        'import_batch_id' => $batchId,
    ]);

    // Worker met device — moet bewaard blijven
    $workerWithDevice = Worker::factory()->create([
        'tenant_id'       => $tenant->id,
        'internal_team_id' => $team->id,
        'import_batch_id' => $batchId,
    ]);

    WorkerDevice::create([
        'tenant_id'    => $tenant->id,
        'worker_id'    => $workerWithDevice->id,
        'device_token' => WorkerDevice::generateToken(),
        'last_seen_at' => now(),
    ]);

    $dto = new DeleteWorkerImportBatchData(importBatchId: $batchId);
    $action = app(DeleteWorkerImportBatchAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeTrue();
    expect($result['deleted_count'])->toBe(1);
    expect($result['preserved_count'])->toBe(1);
    expect($result['total_count'])->toBe(2);

    // Veilige worker verwijderd
    expect(Worker::withoutGlobalScopes()->where('id', $workerSafe->id)->exists())->toBeFalse();

    // Worker met device bewaard
    expect(Worker::withoutGlobalScopes()->where('id', $workerWithDevice->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Scenario 6: Tenant-isolatie — import raakt andere tenant niet
// ---------------------------------------------------------------------------

it('does not affect workers of other tenants during import', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Tenancy::actAs($tenantA->id);
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenantB->id]);
    $existingWorkerB = Worker::factory()->create([
        'tenant_id'       => $tenantB->id,
        'internal_team_id' => $teamB->id,
    ]);

    $csvPath = makeCsvFile([
        ['team_name', 'first_name', 'last_name'],
        ['Team A', 'Koen', 'De Smedt'],
    ]);

    $dto = new ImportWorkersData(filePath: $csvPath, originalName: 'tenant_a.csv');
    $action = app(ImportWorkersAction::class);
    $result = $action->handle($dto, $tenantA->id, $userA->id);

    expect($result['success'])->toBeTrue();

    // Worker van tenant B nog steeds intact
    expect(Worker::withoutGlobalScopes()->where('id', $existingWorkerB->id)->exists())->toBeTrue();

    // Team van tenant B heeft geen nieuwe workers
    expect(Worker::withoutGlobalScopes()->where('internal_team_id', $teamB->id)->count())->toBe(1);

    unlink($csvPath);
});

// ---------------------------------------------------------------------------
// Scenario 7: Tenant-isolatie — undo raakt andere tenant niet (zelfde batchId)
// ---------------------------------------------------------------------------

it('does not delete workers from other tenants on batch undo with same batch id', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $teamA = InternalTeam::factory()->create(['tenant_id' => $tenantA->id]);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenantB->id]);

    $sharedBatchId = 'shared-batch-isolation';

    Worker::factory()->count(2)->create([
        'tenant_id'       => $tenantA->id,
        'internal_team_id' => $teamA->id,
        'import_batch_id' => $sharedBatchId,
    ]);

    Worker::factory()->count(2)->create([
        'tenant_id'       => $tenantB->id,
        'internal_team_id' => $teamB->id,
        'import_batch_id' => $sharedBatchId,
    ]);

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    Tenancy::actAs($tenantA->id);

    $dto = new DeleteWorkerImportBatchData(importBatchId: $sharedBatchId);
    $action = app(DeleteWorkerImportBatchAction::class);
    $result = $action->handle($dto, $tenantA->id, $userA->id);

    expect($result['success'])->toBeTrue();
    expect($result['deleted_count'])->toBe(2);

    // Tenant A workers verwijderd
    expect(Worker::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->where('import_batch_id', $sharedBatchId)->count())->toBe(0);

    // Tenant B workers onaangetast
    expect(Worker::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->where('import_batch_id', $sharedBatchId)->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// Scenario 8: Batch niet gevonden geeft foutmelding
// ---------------------------------------------------------------------------

it('returns error when batch id does not exist', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $dto = new DeleteWorkerImportBatchData(importBatchId: 'non-existent-batch-xyz');
    $action = app(DeleteWorkerImportBatchAction::class);
    $result = $action->handle($dto, $tenant->id, $user->id);

    expect($result['success'])->toBeFalse();
    expect($result['errors'])->not->toBeEmpty();
});
