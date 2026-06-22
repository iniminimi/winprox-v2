<?php

use App\Enums\AdminHealthIssueType;
use App\Livewire\Dashboard;
use App\Livewire\Locations\Index as LocationIndex;
use App\Livewire\Locations\Show as LocationShow;
use App\Livewire\Pages\Health;
use App\Livewire\Pages\Settings;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Admin\AdminHealthService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function seedHealthyTenant(Tenant $tenant): array
{
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $category->teams()->attach($team->id);

    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'street' => 'Kerkstraat',
        'house_number' => '1',
        'postal_code' => '1000',
        'city' => 'Brussel',
        'address' => null,
    ]);

    Storage::fake('public');
    $photoPath = 'units/test-bg.jpg';
    Storage::disk('public')->put($photoPath, 'photo');

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'background_photo_path' => $photoPath,
        'is_active' => true,
    ]);

    return compact('team', 'category', 'location', 'unit');
}

it('rapporteert gezonde tenant wanneer actieve records compleet zijn', function () {
    $tenant = Tenant::factory()->create();
    seedHealthyTenant($tenant);

    $report = app(AdminHealthService::class)->report();

    expect($report->isHealthy())->toBeTrue()
        ->and($report->percentComplete())->toBe(100)
        ->and($report->issues)->toBe([]);
});

it('detecteert units zonder foto, categorieën zonder team en locaties zonder adres', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Losse categorie']);

    Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Zonder adres',
        'address' => null,
        'street' => null,
        'house_number' => null,
        'postal_code' => null,
        'city' => null,
        'is_active' => true,
    ]);

    $locationWithUnit = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'street' => 'Straat',
        'house_number' => '2',
        'postal_code' => '2000',
        'city' => 'Antwerpen',
    ]);

    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $locationWithUnit->id,
        'name' => 'Unit zonder foto',
        'background_photo_path' => null,
        'is_active' => true,
    ]);

    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $locationWithUnit->id,
        'name' => 'Inactieve unit',
        'background_photo_path' => null,
        'is_active' => false,
    ]);

    $report = app(AdminHealthService::class)->report();

    expect($report->isHealthy())->toBeFalse()
        ->and($report->issueCount)->toBe(3)
        ->and(collect($report->issues)->map(fn ($issue) => $issue->type)->all())->toEqualCanonicalizing([
            AdminHealthIssueType::UnitMissingPhoto,
            AdminHealthIssueType::CategoryMissingTeam,
            AdminHealthIssueType::LocationMissingAddress,
        ]);
});

it('toont de gezondheidswidget op het dashboard bij onvolledigheid', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'background_photo_path' => null,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('health.widget.title'))
        ->assertSeeHtml('wp-health-widget');
});

it('verbergt de gezondheidswidget op het dashboard wanneer alles compleet is', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    seedHealthyTenant($tenant);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSeeHtml('wp-health-widget');
});

it('laat medewerkers de health-pagina openen met fix-links', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sanitair']);

    Livewire::actingAs($user)
        ->test(Health::class)
        ->assertSee(__('health.title'))
        ->assertSee('Sanitair')
        ->assertSee(__('health.fix'))
        ->assertSee(route('locations.index', ['edit_category' => $category->id]), false);
});

it('opent de categorie-edit modal via edit_category query parameter', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Elektra']);

    $this->actingAs($user)
        ->get(route('locations.index', ['edit_category' => $category->id]))
        ->assertOk();

    Livewire::actingAs($user)
        ->withQueryParams(['edit_category' => $category->id])
        ->test(LocationIndex::class)
        ->assertSet('showCategoriesModal', true)
        ->assertSet('editingCategoryId', $category->id)
        ->assertSet('categoryName', 'Elektra');
});

it('opent unit- en locatie-edit modals via query parameters', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Hal B']);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lift 1',
    ]);

    Livewire::actingAs($user)
        ->withQueryParams(['edit' => 'location'])
        ->test(LocationShow::class, ['location' => $location])
        ->assertSet('showLocationModal', true);

    Livewire::actingAs($user)
        ->withQueryParams(['edit_unit' => $unit->id])
        ->test(LocationShow::class, ['location' => $location])
        ->assertSet('showUnitModal', true)
        ->assertSet('editingUnitId', $unit->id);
});

it('detecteert units zonder gps en melden uitgeschakeld', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_gps_location' => true,
    ]);
    $category->teams()->attach($team->id);

    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'street' => 'Straat',
        'house_number' => '1',
        'postal_code' => '1000',
        'city' => 'Stad',
        'is_active' => true,
    ]);

    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'name' => 'Zonder GPS',
        'public_reports_enabled' => false,
        'is_active' => true,
    ]);

    $report = app(AdminHealthService::class)->report();

    expect(collect($report->issues)->map(fn ($issue) => $issue->type)->all())->toEqualCanonicalizing([
        AdminHealthIssueType::UnitMissingPhoto,
        AdminHealthIssueType::UnitMissingGps,
        AdminHealthIssueType::UnitPublicReportsDisabled,
    ]);
});

it('toont configuratie-overzicht op instellingen na uitklappen', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Los']);

    Livewire::actingAs($user)
        ->test(Settings::class)
        ->assertSee(__('settings.config_overview.title'))
        ->assertDontSee(__('health.summary.title'))
        ->call('loadConfigOverview')
        ->assertSet('configOverviewLoaded', true)
        ->assertSee(__('health.summary.title'))
        ->assertSee(__('settings.config_overview.open_full'));
});

it('toont kpi-kaders en filterkaarten op de health-pagina', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sanitair']);

    Livewire::actingAs($user)
        ->test(Health::class)
        ->assertSee(__('settings.config_overview.kpi.inactive_locations'))
        ->assertSee(__('health.filter.all'))
        ->assertSeeHtml('wp-config-overview-kpi--filter')
        ->assertSee(__('health.issue.category_team'))
        ->call('setFilter', AdminHealthIssueType::CategoryMissingTeam->value)
        ->assertSet('filter', AdminHealthIssueType::CategoryMissingTeam->value)
        ->assertSee('Sanitair');
});
