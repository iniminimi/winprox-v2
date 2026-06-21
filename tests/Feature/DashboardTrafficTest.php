<?php

use App\Actions\Public\RecordUnitPortalVisitAction;
use App\Livewire\Dashboard;
use App\Livewire\Locations\Show as LocationShow;
use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitPortalVisit;
use App\Models\User;
use App\Support\Dashboard\TopScannedUnitsService;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('logt een unit-portaalbezoek bij mount', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->withQrToken('portal-visit-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'is_active' => true,
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'portal-visit-token']);

    expect(UnitPortalVisit::query()->count())->toBe(1)
        ->and(UnitPortalVisit::first()->unit_id)->toBe($unit->id)
        ->and(UnitPortalVisit::first()->tenant_id)->toBe($tenant->id);
});

it('logt geen bezoek voor inactieve units', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->withQrToken('inactive-portal-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'is_active' => false,
    ]);

    app(RecordUnitPortalVisitAction::class)->handle(
        Unit::withoutGlobalScopes()->where('qr_token', 'inactive-portal-token')->firstOrFail(),
        '127.0.0.1',
    );

    expect(UnitPortalVisit::query()->count())->toBe(0);
});

it('rangschikt top gescande units op portalbezoeken en qr-scans', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $busy = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Busy']);
    $quiet = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Quiet']);

    UnitPortalVisit::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $busy->id,
        'visited_at' => now()->subDay(),
    ]);

    $qrCode = QrCode::factory()->forUnit($quiet)->create(['tenant_id' => $tenant->id]);
    QrScan::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_code_id' => $qrCode->id,
        'scanned_at' => now()->subHours(2),
    ]);

    $rows = app(TopScannedUnitsService::class)->topForCurrentTenant();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->unitName)->toBe('Busy')
        ->and($rows[0]->scanCount)->toBe(3)
        ->and($rows[1]->scanCount)->toBe(1);
});

it('toont de traffic-widget altijd met lege staat zonder scan-data', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSeeHtml('wp-traffic-widget')
        ->assertSee(__('dashboard.traffic.empty'));
});

it('toont de traffic-widget naast de health-widget op het dashboard', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'background_photo_path' => null,
        'is_active' => true,
    ]);

    UnitPortalVisit::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'visited_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSeeHtml('wp-dashboard-widgets')
        ->assertSeeHtml('wp-health-widget')
        ->assertSeeHtml('wp-traffic-widget')
        ->assertSee(__('dashboard.traffic.title'));
});

it('markeert en filtert een unit op locatiedetail via unit_id', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $target = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lift A',
    ]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lift B',
    ]);

    Livewire::actingAs($user)
        ->withQueryParams(['unit_id' => $target->id])
        ->test(LocationShow::class, ['location' => $location])
        ->assertSet('focusUnitId', $target->id)
        ->assertSee('Lift A')
        ->assertSeeHtml('wp-issue-row--focus')
        ->assertSeeHtml('id="unit-row-'.$target->id.'"');
});
