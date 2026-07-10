<?php

use App\Actions\Manual\PrepareManualCaptureTenantAction;
use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Models\User;

it('zet has_esg_module aan voor de capture-tenant', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => false]);
    User::factory()->admin()->for($tenant)->create(['email' => 'capture@example.com']);
    config(['manual_capture.email' => 'capture@example.com']);

    $result = app(PrepareManualCaptureTenantAction::class)->handle();

    expect($result->id)->toBe($tenant->id)
        ->and($tenant->fresh()->has_esg_module)->toBeTrue();
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
        ->and(ClockPoint::query()->where('tenant_id', $tenant->id)->count())->toBe(1);

    $token = app(PrepareManualCaptureTenantAction::class)->clockPointQrToken($tenant->fresh());
    expect($token)->not->toBeNull()->not->toBe('');
});

it('laat has_esg_module ongemoeid wanneer al actief', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    User::factory()->admin()->for($tenant)->create(['email' => 'capture@example.com']);
    config(['manual_capture.email' => 'capture@example.com']);

    app(PrepareManualCaptureTenantAction::class)->handle();

    expect($tenant->fresh()->has_esg_module)->toBeTrue();
});

it('weigert voorbereiden zonder capture-email', function () {
    config(['manual_capture.email' => null]);

    app(PrepareManualCaptureTenantAction::class)->handle();
})->throws(InvalidArgumentException::class, 'manual_capture_not_configured');
