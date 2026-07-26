<?php

use App\Models\Location;
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
    $location = Location::factory()->create(['tenant_id' => $tenantId]);

    DB::table('audit_logs')->insert([
        'tenant_id' => $tenantId,
        'user_id' => null,
        'action' => 'units.import',
        'model_type' => Unit::class,
        'model_id' => null,
        'payload' => json_encode(['count' => 5]),
        'created_at' => now()->subDays(1),
    ]);

    $batches = ImportBatchRegistry::recentBatchesForLocation($tenantId, $location->id);

    expect($batches)->toBeEmpty();
});

it('handles audit logs with null batch_id', function () {
    $tenantId = Tenancy::id();
    $location = Location::factory()->create(['tenant_id' => $tenantId]);

    DB::table('audit_logs')->insert([
        'tenant_id' => $tenantId,
        'user_id' => null,
        'action' => 'units.import',
        'model_type' => Unit::class,
        'model_id' => null,
        'payload' => json_encode(['count' => 5, 'batch_id' => null]),
        'created_at' => now()->subDays(1),
    ]);

    $batches = ImportBatchRegistry::recentBatchesForLocation($tenantId, $location->id);

    expect($batches)->toBeEmpty();
});

it('returns batches with valid batch_id for the location', function () {
    $tenantId = Tenancy::id();
    $batchId = 'test-batch-123';

    $location = Location::factory()->create([
        'tenant_id' => $tenantId,
        'name' => 'Test Location',
    ]);

    Unit::create([
        'tenant_id' => $tenantId,
        'location_id' => $location->id,
        'category_id' => null,
        'name' => 'Test Unit',
        'import_batch_id' => $batchId,
        'is_active' => true,
    ]);

    DB::table('audit_logs')->insert([
        'tenant_id' => $tenantId,
        'user_id' => null,
        'action' => 'units.import',
        'model_type' => Unit::class,
        'model_id' => null,
        'payload' => json_encode([
            'count' => 5,
            'batch_id' => $batchId,
            'file_name' => 'test.csv',
            'location_id' => $location->id,
        ]),
        'created_at' => now()->subDays(1),
    ]);

    $batches = ImportBatchRegistry::recentBatchesForLocation($tenantId, $location->id);

    expect($batches)->toHaveCount(1)
        ->and($batches[0]['batch_id'])->toBe($batchId)
        ->and($batches[0]['file_name'])->toBe('test.csv')
        ->and($batches[0]['unit_count'])->toBe(1);
});

it('filters out batches with zero units', function () {
    $tenantId = Tenancy::id();
    $batchId = 'test-batch-empty';
    $location = Location::factory()->create(['tenant_id' => $tenantId]);

    DB::table('audit_logs')->insert([
        'tenant_id' => $tenantId,
        'user_id' => null,
        'action' => 'units.import',
        'model_type' => Unit::class,
        'model_id' => null,
        'payload' => json_encode([
            'count' => 0,
            'batch_id' => $batchId,
            'file_name' => 'empty.csv',
            'location_id' => $location->id,
        ]),
        'created_at' => now()->subDays(1),
    ]);

    $batches = ImportBatchRegistry::recentBatchesForLocation($tenantId, $location->id);

    expect($batches)->toBeEmpty();
});
