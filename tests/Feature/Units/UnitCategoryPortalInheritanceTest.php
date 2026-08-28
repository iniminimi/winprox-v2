<?php

use App\Actions\Locations\UpdateCategoryAction;
use App\Livewire\Locations\Show;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use App\Support\Units\UnitCategoryPortalInheritance;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('kopieert categorie-portaalvinkjes naar het unit-formulier bij categoriewijziging', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_checks' => true,
        'require_reporter_contact' => true,
        'is_reservable' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openCreateUnit')
        ->set('unitCategoryId', $category->id)
        ->assertSet('unitAllowUnitChecks', true)
        ->assertSet('unitRequireReporterContact', true)
        ->assertSet('unitAllowReservations', true);
});

it('werkt inheriting units bij bij categorie-update wanneer ze nog de oude defaults hadden', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_checks' => false,
        'allow_unit_measurements' => false,
    ]);

    $inheriting = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'allow_unit_checks' => false,
        'allow_unit_measurements' => false,
    ]);

    $overridden = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'allow_unit_checks' => true,
        'allow_unit_measurements' => false,
    ]);

    app(UpdateCategoryAction::class)->handle($category, [
        'name' => $category->name,
        'allow_gps_location' => false,
        'is_reservable' => false,
        'allow_unit_checks' => true,
        'allow_unit_measurements' => true,
        'require_reporter_contact' => false,
        'require_reporter_email_verification' => false,
    ], $admin->id);

    expect($inheriting->fresh()->allow_unit_checks)->toBeTrue()
        ->and($inheriting->fresh()->allow_unit_measurements)->toBeTrue()
        ->and($overridden->fresh()->allow_unit_checks)->toBeTrue()
        ->and($overridden->fresh()->allow_unit_measurements)->toBeFalse();
});

it('herkent wanneer unit-vinkjes afwijken van categorie-defaults', function () {
    $category = Category::factory()->make([
        'allow_unit_checks' => true,
        'is_reservable' => false,
    ]);

    $defaults = UnitCategoryPortalInheritance::defaultsFromCategory($category);

    $unit = Unit::factory()->make([
        'allow_unit_checks' => true,
        'allow_reservations' => false,
        'allow_unit_measurements' => false,
        'require_reporter_contact' => false,
        'require_reporter_email_verification' => false,
    ]);

    expect(UnitCategoryPortalInheritance::unitMatchesDefaults($unit, $defaults))->toBeTrue();

    $unit->allow_unit_checks = false;

    expect(UnitCategoryPortalInheritance::unitMatchesDefaults($unit, $defaults))->toBeFalse();
});
