<?php

declare(strict_types=1);

use App\Actions\Dev\SeedTenantDemoDataAction;
use App\Models\ClockPoint;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Tenant;
use App\Models\WorkShift;

it('seed demo-data voor clock points esg en time', function () {
    $tenant = Tenant::factory()->create([
        'has_esg_module' => false,
        'has_time_module' => false,
    ]);

    $result = app(SeedTenantDemoDataAction::class)->handle($tenant, [
        'clock_points' => 10,
        'esg' => true,
        'time' => true,
    ]);

    $tenant->refresh();

    expect($tenant->has_esg_module)->toBeTrue()
        ->and($tenant->has_time_module)->toBeTrue()
        ->and($result['clock_points_total'])->toBe(10)
        ->and(ClockPoint::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(10)
        ->and(EsgIndicator::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe($result['esg_indicators'])
        ->and(EsgMeasurement::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe($result['esg_measurements'])
        ->and(WorkShift::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe($result['work_shifts']);
});

it('seed demo esg maakt geen dubbele indicatoren bij herhaald draaien', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $seed = app(SeedTenantDemoDataAction::class);

    $seed->handle($tenant, ['clock_points' => 0, 'esg' => true, 'time' => false]);
    $firstCount = EsgIndicator::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

    $seed->handle($tenant, ['clock_points' => 0, 'esg' => true, 'time' => false]);
    $secondCount = EsgIndicator::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

    expect($firstCount)->toBeGreaterThan(0)
        ->and($secondCount)->toBe($firstCount);
});

it('seed esg trends vult dagelijkse numerieke metingen voor de laatste 30 dagen', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $seed = app(SeedTenantDemoDataAction::class);

    $seed->handle($tenant, ['clock_points' => 0, 'esg' => true, 'time' => false]);
    $before = EsgMeasurement::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

    $result = $seed->handle($tenant, [
        'clock_points' => 0,
        'esg' => false,
        'esg_trends' => true,
        'time' => false,
    ]);

    $after = EsgMeasurement::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

    expect($result['esg_trend_measurements'])->toBeGreaterThan(0)
        ->and($after)->toBeGreaterThan($before);

    $seed->handle($tenant, ['clock_points' => 0, 'esg' => false, 'esg_trends' => true, 'time' => false]);

    expect(EsgMeasurement::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe($after);
});

it('seed zet demo-indicatorcategorieen voor bestaande indicatoren', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    EsgIndicator::factory()->numeric('kWh')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Elektriciteit kWh',
        'category' => null,
    ]);
    EsgIndicator::factory()->numeric('m³')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas m³',
        'category' => null,
    ]);

    app(SeedTenantDemoDataAction::class)->handle($tenant, [
        'clock_points' => 0,
        'esg' => false,
        'esg_trends' => true,
        'time' => false,
    ]);

    expect(EsgIndicator::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('category', 'name')->all())
        ->toMatchArray([
            'Elektriciteit kWh' => \App\Enums\EsgIndicatorCategory::Energy,
            'Gas m³' => \App\Enums\EsgIndicatorCategory::Gas,
        ]);
});
