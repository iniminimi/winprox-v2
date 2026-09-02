<?php

declare(strict_types=1);

use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Tenancy;
use App\Support\Ui\UiThemeResolver;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function portalThemeScaffold(): Unit
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Test Category']);
    $category->teams()->sync([$team->id]);

    return Unit::factory()->withQrToken('unit-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);
}

it('past portaal ui-stijl in sessie via livewire', function () {
    portalThemeScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('switchUiTheme', 'dark')
        ->assertDispatched('ui-theme-changed', theme: 'dark');

    expect(session('ui_theme'))->toBe('dark');
    expect(UiThemeResolver::resolvePortal())->toBe('dark');
});

it('toont stijlkeuze op unit portaal', function () {
    portalThemeScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(__('settings.style.options.modern.label'))
        ->assertSee(__('settings.style.options.simple.label'))
        ->assertSee(__('settings.style.options.dark.label'));
});
