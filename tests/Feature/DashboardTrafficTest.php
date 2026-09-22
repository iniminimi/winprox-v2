<?php

use App\Actions\Public\RecordUnitPortalVisitAction;
use App\Livewire\Dashboard;
use App\Livewire\Locations\Show as LocationShow;
use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Models\UnitPortalVisit;
use App\Models\User;
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

it('toont geen populaire-assets-widget op het dashboard', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'background_photo_path' => null,
        'is_active' => true,
    ]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    UnitPortalVisit::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'visited_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSeeHtml('wp-traffic-widget')
        ->assertDontSeeHtml('wp-health-widget');
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
