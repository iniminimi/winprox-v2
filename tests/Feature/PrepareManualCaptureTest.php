<?php

use App\Actions\Manual\PrepareManualCaptureTenantAction;
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
