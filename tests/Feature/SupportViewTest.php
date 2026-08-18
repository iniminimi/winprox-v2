<?php

use App\Livewire\Platform\Tenants;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SupportTenantContext;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(function () {
    SupportTenantContext::stop();
    Tenancy::forget();
});

it('stuurt superuser zonder support view door naar platform', function () {
    $super = User::factory()->superuser()->create();

    $this->actingAs($super)
        ->get(route('dashboard'))
        ->assertRedirect(route('platform.tenants'));
});

it('beperkt superuser in support view tot de gekozen tenant', function () {
    $super = User::factory()->superuser()->create();
    $tenantA = Tenant::factory()->create(['name' => 'Org A']);
    $tenantB = Tenant::factory()->create(['name' => 'Org B']);
    seedTenantPastOnboarding($tenantA);

    Issue::factory()->create(['tenant_id' => $tenantA->id, 'description' => 'Melding tenant A']);
    $issueB = Issue::factory()->create(['tenant_id' => $tenantB->id, 'description' => 'Melding tenant B']);

    Livewire::actingAs($super)
        ->test(Tenants::class)
        ->call('startSupport', $tenantA->id)
        ->assertRedirect(route('dashboard'));

    $this->actingAs($super)
        ->get(route('issues.index'))
        ->assertOk()
        ->assertSee('Melding tenant A')
        ->assertDontSee('Melding tenant B');

    $this->actingAs($super)
        ->get(route('issues.show', $issueB))
        ->assertNotFound();
});

it('beëindigt support view via de banner en toont daarna platform', function () {
    $super = User::factory()->superuser()->create();
    $tenant = Tenant::factory()->create(['name' => 'Org Stop']);
    seedTenantPastOnboarding($tenant);

    Livewire::actingAs($super)
        ->test(Tenants::class)
        ->call('startSupport', $tenant->id)
        ->assertRedirect(route('dashboard'));

    $this->actingAs($super)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('platform.banner', ['name' => $tenant->name]), false)
        ->assertSee('action="'.route('platform.support.stop').'"', false);

    expect(SupportTenantContext::isActive())->toBeTrue();

    $this->actingAs($super)
        ->post(route('platform.support.stop'))
        ->assertRedirect(route('platform.tenants'));

    expect(SupportTenantContext::isActive())->toBeFalse();

    $this->actingAs($super)
        ->get(route('platform.tenants'))
        ->assertOk()
        ->assertDontSee(__('platform.banner', ['name' => $tenant->name]), false)
        ->assertSee(__('platform.tenants_nav'));
});

it('beëindigt support view vanaf de tenantlijst en herlaadt platform', function () {
    $super = User::factory()->superuser()->create();
    $tenant = Tenant::factory()->create(['name' => 'Org Livewire Stop']);

    Livewire::actingAs($super)
        ->test(Tenants::class)
        ->call('startSupport', $tenant->id)
        ->assertRedirect(route('dashboard'));

    expect(SupportTenantContext::isActive())->toBeTrue();

    Livewire::actingAs($super)
        ->test(Tenants::class)
        ->call('stopSupport')
        ->assertRedirect(route('platform.tenants'));

    expect(SupportTenantContext::isActive())->toBeFalse();
});

it('blokkeert platform voor normale gebruikers', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('platform.tenants'))
        ->assertForbidden();
});
