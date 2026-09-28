<?php

use App\Actions\Manual\PrepareManualCaptureTenantAction;
use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDevice;

it('zet has_esg_module aan voor de capture-tenant', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => false]);
    User::factory()->admin()->for($tenant)->create(['email' => 'capture@example.com']);
    config(['manual_capture.email' => 'capture@example.com']);

    $result = app(PrepareManualCaptureTenantAction::class)->handle();

    expect($result->id)->toBe($tenant->id)
        ->and($tenant->fresh()->has_esg_module)->toBeTrue();
});

it('zet has_iot_module aan voor de capture-tenant', function () {
    $tenant = Tenant::factory()->create(['has_iot_module' => false]);
    User::factory()->admin()->for($tenant)->create(['email' => 'capture@example.com']);
    config(['manual_capture.email' => 'capture@example.com']);

    $result = app(PrepareManualCaptureTenantAction::class)->handle();

    expect($result->id)->toBe($tenant->id)
        ->and($tenant->fresh()->has_iot_module)->toBeTrue();
});

it('zet has_time_module aan en maakt een clock point aan voor de capture-tenant', function () {
    $tenant = Tenant::factory()->create([
        'has_esg_module' => true,
        'has_time_module' => false,
    ]);
    User::factory()->admin()->for($tenant)->create(['email' => 'capture@example.com']);
    config(['manual_capture.email' => 'capture@example.com']);

    $result = app(PrepareManualCaptureTenantAction::class)->handle();

    expect($result->fresh()->has_time_module)->toBeTrue()
        ->and($result->fresh()->time_gps_visits)->toBeTrue()
        ->and(ClockPoint::query()->where('tenant_id', $tenant->id)->count())->toBe(1);

    $token = app(PrepareManualCaptureTenantAction::class)->clockPointQrToken($tenant->fresh());
    expect($token)->not->toBeNull()->not->toBe('');
});

it('vernieuwt de trial wanneer de capture-tenant geen app-toegang meer heeft', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'billing_plan' => null,
        'billing_active_until' => null,
        'is_active' => true,
        'has_esg_module' => true,
        'has_time_module' => true,
        'has_iot_module' => true,
    ]);
    User::factory()->admin()->for($tenant)->create(['email' => 'capture@example.com']);
    config(['manual_capture.email' => 'capture@example.com']);

    expect($tenant->hasFullAppAccess())->toBeFalse();

    $result = app(PrepareManualCaptureTenantAction::class)->handle();

    expect($result->fresh()->hasFullAppAccess())->toBeTrue()
        ->and($result->fresh()->trial_ends_at?->isFuture())->toBeTrue();
});

it('laat has_esg_module ongemoeid wanneer al actief', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    User::factory()->admin()->for($tenant)->create(['email' => 'capture@example.com']);
    config(['manual_capture.email' => 'capture@example.com']);

    app(PrepareManualCaptureTenantAction::class)->handle();

    expect($tenant->fresh()->has_esg_module)->toBeTrue();
});

it('geeft het kloktoestel van de capture-worker vrij', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true, 'has_iot_module' => true]);
    User::factory()->admin()->for($tenant)->create(['email' => 'capture@example.com']);
    config([
        'manual_capture.email' => 'capture@example.com',
        'manual_capture.worker_first_name' => 'John',
        'manual_capture.worker_last_name' => 'Workman',
    ]);

    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'John',
        'last_name' => 'Workman',
    ]);
    $device = WorkerDevice::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
    ]);
    $worker->forceFill(['clock_device_id' => $device->id])->save();

    app(PrepareManualCaptureTenantAction::class)->handle();

    // Zonder vrijgave weigert elke nieuwe browser-sessie de worker-sign-in
    // (één gsm per uitvoerder); de eerste Playwright-sign-in bindt opnieuw.
    expect($worker->fresh()->clock_device_id)->toBeNull()
        ->and(WorkerDevice::withoutGlobalScope('tenant')->where('worker_id', $worker->id)->count())->toBe(0);
});

it('weigert voorbereiden zonder capture-email', function () {
    config(['manual_capture.email' => null]);

    app(PrepareManualCaptureTenantAction::class)->handle();
})->throws(InvalidArgumentException::class, 'manual_capture_not_configured');
