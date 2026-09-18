<?php

use App\Actions\Locations\BulkCreateUnitsAction;
use App\Livewire\Locations\Show;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\UnitCheckList;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('schrijft gedeelde portaalvinkjes op elke bulk-unit', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $result = app(BulkCreateUnitsAction::class)->handle($location, [
        'ranges' => [
            ['start' => '01', 'count' => 2, 'padding' => '', 'prefix' => 'Kamer ', 'suffix' => ''],
        ],
        'public_reports_enabled' => false,
        'allow_unit_checks' => true,
        'require_reporter_contact' => true,
    ], (int) $tenant->id, (int) $admin->id);

    expect($result['created'])->toBe(2);

    $units = $location->units()->orderBy('name')->get();
    expect($units)->toHaveCount(2);

    foreach ($units as $unit) {
        expect($unit->public_reports_enabled)->toBeFalse()
            ->and($unit->allow_unit_checks)->toBeTrue()
            ->and($unit->require_reporter_contact)->toBeTrue()
            ->and($unit->allow_reservations)->toBeFalse();
    }
});

it('koppelt een checklist aan alle units in de bulk', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $list = UnitCheckList::factory()->create(['tenant_id' => $tenant->id]);

    app(BulkCreateUnitsAction::class)->handle($location, [
        'ranges' => [
            ['start' => '1', 'count' => 2, 'padding' => '', 'prefix' => 'U', 'suffix' => ''],
        ],
        'allow_unit_checks' => true,
        'unit_check_list_id' => $list->id,
    ], (int) $tenant->id, (int) $admin->id);

    expect($location->units()->pluck('unit_check_list_id')->unique()->all())->toBe([(int) $list->id]);
});

it('kopieert categorie-portaalvinkjes naar het bulk-formulier', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
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
        ->call('openBulkModal')
        ->set('bulkCategoryId', $category->id)
        ->assertSet('bulkAllowUnitChecks', true)
        ->assertSet('bulkRequireReporterContact', true)
        ->assertSet('bulkAllowReservations', true);
});

it('maakt bulk-units via Livewire met overschreven portaalvinkjes', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openBulkModal')
        ->set('bulkRanges', [
            ['start' => '10', 'count' => 2, 'padding' => '', 'prefix' => 'Lokaal ', 'suffix' => ''],
        ])
        ->set('bulkPublicReportsEnabled', false)
        ->set('bulkAllowUnitChecks', true)
        ->call('createBulk')
        ->assertHasNoErrors();

    $units = $location->units()->orderBy('name')->get();
    expect($units->pluck('name')->all())->toBe(['Lokaal 10', 'Lokaal 11']);
    foreach ($units as $unit) {
        expect($unit->public_reports_enabled)->toBeFalse()
            ->and($unit->allow_unit_checks)->toBeTrue();
    }
});
