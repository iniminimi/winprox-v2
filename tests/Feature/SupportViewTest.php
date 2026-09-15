<?php

use App\Livewire\Locations\Index as LocationIndex;
use App\Livewire\Platform\Tenants;
use App\Models\Category;
use App\Models\InternalTeam;
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

it('laat superuser in support view een categorie bekijken zonder te wijzigen', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $super = User::factory()->superuser()->create();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kamers',
        'show_previous_issues' => false,
    ]);
    $category->teams()->sync([$team->id]);

    Livewire::actingAs($super)
        ->test(Tenants::class)
        ->call('startSupport', $tenant->id)
        ->assertRedirect(route('dashboard'));

    Livewire::actingAs($super)
        ->test(LocationIndex::class, ['section' => 'categories'])
        ->assertSee('Kamers')
        ->assertSee(__('common.button.view'))
        ->assertDontSee(__('locations.categories.add'))
        ->call('openEditCategory', $category->id)
        ->assertSet('showCategoriesModal', true)
        ->assertSet('categoryName', 'Kamers')
        ->assertSet('categoryShowPreviousIssues', false)
        ->assertSee(__('locations.categories.view_title'))
        ->assertDontSee(__('common.button.save'))
        ->call('saveCategory')
        ->assertForbidden();

    expect($category->fresh()->name)->toBe('Kamers')
        ->and($category->fresh()->show_previous_issues)->toBeFalse();
});

it('blokkeert platform voor normale gebruikers', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('platform.tenants'))
        ->assertForbidden();
});
