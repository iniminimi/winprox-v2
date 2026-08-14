<?php

use App\Livewire\Locations\Index;
use App\Models\CategoryTranslation;
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
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $admin];
}

it('toont op Locaties geen categorieënlijst', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSet('section', 'locations')
        ->assertSee(__('locations.title'))
        ->assertSee(\App\Support\PageHelp::for('locations.list')['title'], false)
        ->assertDontSee('Onderhoud')
        ->assertDontSee(__('locations.categories.add'));
});

it('toont op Categorieën alleen categorieën', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);
    \App\Models\Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Magazijn']);

    Livewire::actingAs($admin)
        ->test(Index::class, ['section' => 'categories'])
        ->assertSet('section', 'categories')
        ->assertSee(__('locations.categories.title'))
        ->assertSee('Onderhoud')
        ->assertSee(__('common.button.edit'))
        ->assertSee(__('common.button.delete'))
        ->assertSee(\App\Support\PageHelp::for('locations.categories')['title'], false)
        ->assertDontSee('Magazijn')
        ->assertDontSee(__('locations.add'));
});

it('pulst de categorie-knop wanneer er nog geen categorieën zijn', function () {
    [, $admin] = setupTenantAdminForLocations();

    Livewire::actingAs($admin)
        ->test(Index::class, ['section' => 'categories'])
        ->assertSee(__('locations.categories.empty'), false)
        ->assertSeeHtml('wp-btn--prio-pulse');
});

it('pulst de categorie-knop niet wanneer er categorieën zijn', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);

    Livewire::actingAs($admin)
        ->test(Index::class, ['section' => 'categories'])
        ->assertDontSeeHtml('wp-btn--prio-pulse');
});

it('verwijst van Locaties naar Categorieën wanneer er nog geen categorieën zijn', function () {
    [, $admin] = setupTenantAdminForLocations();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee(__('locations.onboarding.title_categories'), false)
        ->assertSee(__('locations.onboarding.go_to_categories'), false)
        ->assertSee(route('locations.index', ['section' => 'categories']), false)
        ->assertDontSeeHtml('wp-btn--prio-pulse');
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
        ->assertDontSeeHtml('wp-btn--prio-pulse');
});

it('laat een admin een categorie bewerken via Categorieën', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Oud']);

    Livewire::actingAs($admin)
        ->test(Index::class, ['section' => 'categories'])
        ->call('openEditCategory', $category->id)
        ->assertSet('editingCategoryId', $category->id)
        ->assertSet('showCategoriesModal', true)
        ->assertSet('categoryName', 'Oud')
        ->set('categoryName', 'Nieuw')
        ->set('selectedCategoryTeamIds', [$team->id])
        ->call('saveCategory')
        ->assertHasNoErrors()
        ->assertSet('showCategoriesModal', false)
        ->assertSet('editingCategoryId', null);

    expect($category->fresh()->name)->toBe('Nieuw');
});

it('sluit de categorie-modal na annuleren tijdens bewerken', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Oud']);

    Livewire::actingAs($admin)
        ->test(Index::class, ['section' => 'categories'])
        ->call('openEditCategory', $category->id)
        ->assertSet('showCategoriesModal', true)
        ->call('cancelEditCategory')
        ->assertSet('showCategoriesModal', false)
        ->assertSet('editingCategoryId', null);
});

it('laat een admin een categorievertaling opslaan vanuit bewerken', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Voertuigen',
        'original_language' => 'nl',
    ]);
    $category->teams()->sync([$team->id]);

    Livewire::actingAs($admin)
        ->test(Index::class, ['section' => 'categories'])
        ->call('openEditCategory', $category->id)
        ->set('categoryPreviewLocale', 'en')
        ->set('categoryTranslationName', 'Vehicles')
        ->call('saveCategoryTranslationOverride')
        ->assertHasNoErrors();

    $translation = CategoryTranslation::query()
        ->where('category_id', $category->id)
        ->where('locale', 'en')
        ->first();

    expect($translation)->not->toBeNull()
        ->and($translation?->name)->toBe('Vehicles');
});

it('laat een admin een categorie verwijderen vanuit Categorieën', function () {
    [$tenant, $admin] = setupTenantAdminForLocations();
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'TeVerwijderen']);

    Livewire::actingAs($admin)
        ->test(Index::class, ['section' => 'categories'])
        ->call('deleteCategory', $category->id)
        ->assertHasNoErrors();

    expect(Category::find($category->id))->toBeNull();
});
