<?php

use App\Actions\Locations\DeleteLocationAction;
use App\Actions\Locations\DeleteUnitAction;
use App\Actions\Retention\PruneInactiveTenantFacilityDataAction;
use App\Enums\EsgIndicatorType;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Units\UnitDeletionGuard;
use Illuminate\Support\Facades\Schema;

it('blokkeert unit-verwijdering wanneer er ESG-metingen zijn', function () {
    $tenant = Tenant::factory()->create();
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id]);
    $indicator = EsgIndicator::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'location_id' => $unit->location_id,
        'esg_indicator_id' => $indicator->id,
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
    ]);

    EsgMeasurement::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'location_id' => $unit->location_id,
        'task_id' => $task->id,
        'esg_indicator_id' => $indicator->id,
        'value_numeric' => 123.45,
        'recorded_at' => now(),
    ]);

    expect(UnitDeletionGuard::blockReason($unit))->toBe(UnitDeletionGuard::BLOCK_HAS_ESG_MEASUREMENTS);

    app(DeleteUnitAction::class)->handle($unit);
})->throws(InvalidArgumentException::class);

it('blokkeert locatie-verwijdering wanneer er ESG-metingen zijn', function () {
    $tenant = Tenant::factory()->create();
    $emptyLocation = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unitLocation = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $unitLocation->id,
    ]);
    $indicator = EsgIndicator::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'location_id' => $unitLocation->id,
        'esg_indicator_id' => $indicator->id,
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
    ]);

    EsgMeasurement::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'location_id' => $emptyLocation->id,
        'task_id' => $task->id,
        'esg_indicator_id' => $indicator->id,
        'value_numeric' => 456.78,
        'recorded_at' => now(),
    ]);

    app(DeleteLocationAction::class)->handle($emptyLocation);
})->throws(InvalidArgumentException::class, 'location_has_esg_measurements');

it('prunt geen meldingen van tenants met has_esg_module', function () {
    config(['data_retention.inactive_tenant_days' => 90]);

    $tenant = Tenant::factory()->create([
        'is_active' => false,
        'has_esg_module' => true,
        'billing_active_until' => now()->subDays(120),
    ]);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);

    $stats = app(PruneInactiveTenantFacilityDataAction::class)->handle(dryRun: false);

    expect($stats['tenants_scanned'])->toBe(0)
        ->and($stats['issues_removed'])->toBe(0)
        ->and(Issue::query()->withoutGlobalScopes()->find($issue->id))->not->toBeNull();
});

it('heeft geen updated_at kolom op esg_measurements', function () {
    expect(Schema::hasColumn('esg_measurements', 'updated_at'))->toBeFalse();
});

it('koppelt issue aan esg indicator', function () {
    $tenant = Tenant::factory()->create();
    $indicator = EsgIndicator::factory()->numeric('m3')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas m3',
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'esg_indicator_id' => $indicator->id,
    ]);

    $issue->load('esgIndicator');

    expect($issue->esgIndicator)->not->toBeNull()
        ->and($issue->esgIndicator->id)->toBe($indicator->id)
        ->and($issue->esgIndicator->type)->toBe(EsgIndicatorType::Numeric)
        ->and($issue->esgIndicator->unit_of_measure)->toBe('m3');
});
