<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('shows only IoT Connect in AUTOMATISERING when tenant has IoT but no ESG', function () {
    $tenant = Tenant::factory()->create([
        'has_iot_module' => true,
        'has_esg_module' => false,
    ]);
    seedTenantPastOnboarding($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();

    expect($response->getContent())->toContain(__('common.nav.iot'))
        ->and($response->getContent())->not->toContain(__('common.nav.esg'));
});

