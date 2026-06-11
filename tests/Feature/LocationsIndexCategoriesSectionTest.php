<?php

use App\Livewire\Locations\Index;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{0: Tenant, 1: User}
 */
function setupTenantAdminForLocations(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Acme NV']);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $admin];
}

it('toont de categorieën-sectie standaard ingeklapt', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);

    $component = Livewire::actingAs($admin)->test(Index::class);

    $component->assertSet('showCategoriesSection', false)
        ->assertSee(__('locations.categories.title'))
        ->assertDontSee('Onderhoud');
});

it('pulst het categorieën-kader wanneer er nog geen categorieën zijn en de sectie ingeklapt is', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSet('showCategoriesSection', false)
        ->assertSee(__('locations.onboarding.title_categories'), false)
        ->assertSeeHtml('wp-card--prio-pulse');
});

it('pulst het categorieën-kader niet wanneer er categorieën zijn', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertDontSeeHtml('wp-card--prio-pulse');
});

it('toont locaties-onboarding en pulst de locatie-knop wanneer er categorieën zijn maar nog geen locaties', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee(__('locations.onboarding.title_locations'), false)
        ->assertDontSee(__('locations.onboarding.title_categories'), false)
        ->assertSeeHtml('wp-btn--prio-pulse');
});

it('verbergt onboarding en pulst niet wanneer er minstens één locatie is', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);
    \App\Models\Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Magazijn']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertDontSee(__('locations.onboarding.title_locations'), false)
        ->assertDontSee(__('locations.onboarding.title_categories'), false)
        ->assertDontSeeHtml('wp-btn--prio-pulse')
        ->assertDontSeeHtml('wp-card--prio-pulse');
});

it('pulst het categorieën-kader niet wanneer de sectie is uitgeklapt', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showCategoriesSection', true)
        ->assertDontSeeHtml('wp-card--prio-pulse');
});

it('toont categorieën met bewerken/verwijderen knoppen na uitklappen', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showCategoriesSection', true)
        ->assertSee('Onderhoud')
        ->assertSee(__('common.button.edit'))
        ->assertSee(__('common.button.delete'));
});

it('laat een admin een categorie bewerken via de sectie', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Oud']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showCategoriesSection', true)
        ->call('openEditCategory', $category->id)
        ->assertSet('editingCategoryId', $category->id)
        ->assertSet('categoryName', 'Oud')
        ->set('categoryName', 'Nieuw')
        ->set('selectedCategoryTeamIds', [$team->id])
        ->call('saveCategory')
        ->assertHasNoErrors();

    expect($category->fresh()->name)->toBe('Nieuw');
});

it('laat een admin een categorie verwijderen vanuit de sectie', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'TeVerwijderen']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showCategoriesSection', true)
        ->call('deleteCategory', $category->id)
        ->assertHasNoErrors();

    expect(Category::find($category->id))->toBeNull();
});
