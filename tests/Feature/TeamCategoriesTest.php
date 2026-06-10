<?php

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
function setupTenantAndAdmin(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Acme NV']);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $admin];
}

it('koppelt categorieën bij het aanmaken van een team', function () {
    [$tenant, $admin] = setupTenantAndAdmin();
    $categoryA = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);
    $categoryB = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Schoonmaak']);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Team::class)
        ->call('openCreateTeam')
        ->set('teamName', 'Technisch team')
        ->set('selectedCategoryIds', [$categoryA->id, $categoryB->id])
        ->call('saveTeam')
        ->assertHasNoErrors();

    $team = InternalTeam::where('name', 'Technisch team')->first();
    expect($team)->not->toBeNull();
    expect($team->categories()->pluck('categories.id')->toArray())
        ->toContain($categoryA->id)
        ->toContain($categoryB->id);
});

it('laadt bestaande categorieën bij bewerken en synchroniseert ze', function () {
    [$tenant, $admin] = setupTenantAndAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $categoryA = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);
    $categoryB = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Schoonmaak']);
    $team->categories()->attach([$categoryA->id, $categoryB->id]);

    $categoryC = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Beveiliging']);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Team::class)
        ->call('openEditTeam', $team->id)
        ->assertSet('selectedCategoryIds', [$categoryA->id, $categoryB->id])
        ->set('selectedCategoryIds', [$categoryB->id, $categoryC->id])
        ->call('saveTeam')
        ->assertHasNoErrors();

    $fresh = $team->fresh();
    expect($fresh->categories()->pluck('categories.id')->toArray())
        ->toContain($categoryB->id)
        ->toContain($categoryC->id)
        ->not->toContain($categoryA->id);
});

it('filtert categorieën van andere tenants bij synchronisatie', function () {
    [$tenant, $admin] = setupTenantAndAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $ownCategory = Category::factory()->create(['tenant_id' => $tenant->id]);

    $otherTenant = Tenant::factory()->create();
    $otherCategory = Category::factory()->create(['tenant_id' => $otherTenant->id]);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Team::class)
        ->call('openEditTeam', $team->id)
        ->set('selectedCategoryIds', [$ownCategory->id, $otherCategory->id])
        ->call('saveTeam')
        ->assertHasNoErrors();

    expect($team->fresh()->categories()->pluck('categories.id')->toArray())
        ->toContain($ownCategory->id)
        ->not->toContain($otherCategory->id);
});

it('laat een medewerker categorieën synchroniseren', function () {
    [$tenant] = setupTenantAndAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($employee)
        ->test(\App\Livewire\Pages\Team::class)
        ->call('openEditTeam', $team->id)
        ->set('selectedCategoryIds', [$category->id])
        ->call('saveTeam')
        ->assertHasNoErrors();

    expect($team->fresh()->categories()->pluck('categories.id')->toArray())
        ->toContain($category->id);
});
