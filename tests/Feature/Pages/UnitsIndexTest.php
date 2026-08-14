<?php

use App\Livewire\Pages\UnitsIndex;
use App\Models\Category;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('filters tenant units by location in UnitsIndex', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $locationA = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $locationB = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

    $unitA = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $locationA->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $locationB->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);

    Livewire::actingAs($user)
        ->test(UnitsIndex::class)
        ->set('locationFilter', $locationA->id)
        ->assertSee($unitA->name)
        ->assertDontSee($unitB->name);
});

it('filters tenant units by category in UnitsIndex', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $categoryA = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sanitair']);
    $categoryB = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Generatoren']);

    $unitA = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $categoryA->id,
        'name' => 'Douchecabine',
        'is_active' => true,
    ]);
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $categoryB->id,
        'name' => 'Stroomgroep',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(UnitsIndex::class)
        ->assertSee(__('units.filters.label'))
        ->assertSee(__('units.filters.count', ['count' => 2]))
        ->set('categoryFilter', $categoryA->id)
        ->assertSee($unitA->name)
        ->assertDontSee($unitB->name)
        ->assertSee(__('units.filters.count', ['count' => 1]));
});

it('toont locatie en categorie op één metaregel zonder dubbele Units-titel', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Industriepark Lille',
        'is_active' => true,
    ]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Sanitair en welfare units',
    ]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'name' => 'Welfare unit Portakabin Shower 2 cabines #1200',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(UnitsIndex::class)
        ->assertSee($unit->name)
        ->assertSee(__('units.row.meta', [
            'location' => 'Industriepark Lille',
            'category' => 'Sanitair en welfare units',
        ]))
        ->assertDontSeeHtml('wp-section-title');
});

