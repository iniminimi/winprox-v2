<?php

use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Tenancy;
use App\Support\Units\ImportBatchRegistry;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
});

it('handles old audit logs without batch_id', function () {
    $tenantId = Tenancy::id();

    // Create an old audit log without batch_id
    DB::table('audit_logs')->insert([
        'tenant_id' => $tenantId,
        'user_id' => null,
        'action' => 'units.import',
        'model_type' => Unit::class,
        'model_id' => null,
        'payload' => json_encode(['count' => 5]), // No batch_id
        'created_at' => now()->subDays(1),
    ]);

    $batches = ImportBatchRegistry::recentBatchesForTenant($tenantId);

    expect($batches)->toBeEmpty();
});

it('handles audit logs with null batch_id', function () {
    $tenantId = Tenancy::id();

    // Create an audit log with explicit null batch_id
    DB::table('audit_logs')->insert([
        'tenant_id' => $tenantId,
        'user_id' => null,
        'action' => 'units.import',
        'model_type' => Unit::class,
        'model_id' => null,
        'payload' => json_encode(['count' => 5, 'batch_id' => null]),
        'created_at' => now()->subDays(1),
    ]);

    $batches = ImportBatchRegistry::recentBatchesForTenant($tenantId);

    expect($batches)->toBeEmpty();
});

it('returns batches with valid batch_id', function () {
    $tenantId = Tenancy::id();
    $batchId = 'test-batch-123';

    // Create a location first
    $location = \App\Models\Location::create([
        'tenant_id' => $tenantId,
        'name' => 'Test Location',
    ]);

    // Create a unit with the batch_id
    Unit::create([
        'tenant_id' => $tenantId,
        'location_id' => $location->id,
        'category_id' => null,
        'name' => 'Test Unit',
        'import_batch_id' => $batchId,
        'is_active' => true,
    ]);

    // Create an audit log with valid batch_id
    DB::table('audit_logs')->insert([
        'tenant_id' => $tenantId,
        'user_id' => null,
        'action' => 'units.import',
        'model_type' => Unit::class,
        'model_id' => null,
        'payload' => json_encode(['count' => 5, 'batch_id' => $batchId, 'file_name' => 'test.csv']),
        'created_at' => now()->subDays(1),
    ]);

    $batches = ImportBatchRegistry::recentBatchesForTenant($tenantId);

    expect($batches)->toHaveCount(1);
    expect($batches[0]['batch_id'])->toBe($batchId);
    expect($batches[0]['file_name'])->toBe('test.csv');
    expect($batches[0]['unit_count'])->toBe(1);
});

it('filters out batches with zero units', function () {
    $tenantId = Tenancy::id();
    $batchId = 'test-batch-empty';

    // Create an audit log with valid batch_id but no units
    DB::table('audit_logs')->insert([
        'tenant_id' => $tenantId,
        'user_id' => null,
        'action' => 'units.import',
        'model_type' => Unit::class,
        'model_id' => null,
        'payload' => json_encode(['count' => 0, 'batch_id' => $batchId, 'file_name' => 'empty.csv']),
        'created_at' => now()->subDays(1),
    ]);

    $batches = ImportBatchRegistry::recentBatchesForTenant($tenantId);

    expect($batches)->toBeEmpty();
});
