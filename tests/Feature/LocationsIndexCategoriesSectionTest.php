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
