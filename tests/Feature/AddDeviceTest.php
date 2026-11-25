<?php

declare(strict_types=1);

use App\Actions\Time\SubmitClockDisplayClaimAction;
use App\Enums\ClockDisplayClaimStatus;
use App\Livewire\AddDevice;
use App\Livewire\Dashboard;
use App\Models\Camera;
use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Screen;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function addDeviceTenant(bool $time = true): Tenant
{
    return Tenant::factory()->create([
        'has_time_module' => $time,
        'checkmate_mode' => false,
    ]);
}

function addDeviceReady(Tenant $tenant): void
{
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
}

it('toont Toestel toevoegen naast Uitvoerders bekijken', function () {
    $tenant = addDeviceTenant();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);
    addDeviceReady($tenant);

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSeeInOrder([
            __('dashboard.intent.tiles.workers.title'),
            __('dashboard.add_device.title'),
        ])
        ->assertSee(__('dashboard.add_device.body'));
});

it('verbergt Toestel toevoegen voor een medewerker en voor Checkmate', function () {
    $tenant = addDeviceTenant();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);
    addDeviceReady($tenant);

    Livewire::actingAs($employee)
        ->test(Dashboard::class)
        ->assertDontSee(__('dashboard.add_device.body'));

    $checkmate = Tenant::factory()->create([
        'checkmate_mode' => true,
        'has_time_module' => false,
        'customers_on_location' => true,
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $checkmate->id]);
    Tenancy::actAs($checkmate->id);

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertDontSee(__('dashboard.add_device.body'));

    Livewire::actingAs($admin)
        ->test(AddDevice::class)
        ->call('openModal')
        ->assertForbidden();
});

it('geeft een prikklokcode voor het gekozen Clock Point en bevestigt de aanvraag', function () {
    $tenant = addDeviceTenant();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);
    $gate = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Poort']);
    $back = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Achterkant']);
    $here = ClockPoint::factory()->forLocation($gate)->create(['name' => 'Ingang']);
    $elsewhere = ClockPoint::factory()->forLocation($back)->create(['name' => 'Achterdeur']);

    $component = Livewire::actingAs($admin)
        ->test(AddDevice::class)
        ->call('openModal')
        ->assertSee(__('dashboard.add_device.type_clock'))
        ->set('deviceType', 'clock')
        ->assertSee('Poort')
        ->assertDontSee('Ingang')
        ->set('locationId', $gate->id)
        ->assertSee('Ingang')
        ->assertDontSee('Achterdeur')
        ->set('placeId', $here->id)
        ->call('issue');

    $here->refresh();
    $shown = substr((string) $here->display_pairing_code, 0, 4).'-'.substr((string) $here->display_pairing_code, 4);
    $component->assertSee($shown);

    $claim = app(SubmitClockDisplayClaimAction::class)->handle((string) $here->display_pairing_code, 'aabbccddeeff', null);

    Livewire::actingAs($admin)
        ->test(AddDevice::class)
        ->set('open', true)
        ->set('deviceType', 'clock')
        ->set('locationId', $back->id)
        ->set('placeId', $elsewhere->id)
        ->call('confirmClaim', $claim->id)
        ->assertNotFound();

    Livewire::actingAs($admin)
        ->test(AddDevice::class)
        ->set('open', true)
        ->set('deviceType', 'clock')
        ->set('locationId', $gate->id)
        ->set('placeId', $here->id)
        ->assertSee('aabbccddeeff')
        ->call('confirmClaim', $claim->id)
        ->assertSee(__('dashboard.add_device.confirmed'));

    expect($here->fresh()->hasLinkedDisplay())->toBeTrue()
        ->and($claim->fresh()->status)->toBe(ClockDisplayClaimStatus::Confirmed);
});

it('weigert een prikklokaanvraag in hetzelfde venster', function () {
    $tenant = addDeviceTenant();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $point = ClockPoint::factory()->forLocation($location)->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(AddDevice::class)
        ->set('deviceType', 'clock')
        ->set('locationId', $location->id)
        ->set('placeId', $point->id)
        ->call('issue');

    $point->refresh();
    $claim = app(SubmitClockDisplayClaimAction::class)->handle((string) $point->display_pairing_code, '112233445566', null);

    Livewire::actingAs($admin)
        ->test(AddDevice::class)
        ->set('open', true)
        ->set('deviceType', 'clock')
        ->set('locationId', $location->id)
        ->set('placeId', $point->id)
        ->call('denyClaim', $claim->id)
        ->assertSee(__('dashboard.add_device.denied'));

    expect($claim->fresh()->status)->toBe(ClockDisplayClaimStatus::Denied)
        ->and($point->fresh()->hasLinkedDisplay())->toBeFalse();
});

it('koppelt een lokaalscherm en een camera aan een unit, niet aan een andere organisatie', function () {
    $tenant = addDeviceTenant(false);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Magazijn']);
    $office = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kantoor']);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Hal']);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $office->id, 'name' => 'Balie']);
    $other = Unit::factory()->create();

    Livewire::actingAs($admin)
        ->test(AddDevice::class)
        ->call('openModal')
        ->assertDontSee(__('dashboard.add_device.type_clock'))
        ->set('deviceType', 'screen')
        ->assertSee('Magazijn')
        ->assertDontSee('Hal')
        ->set('locationId', $location->id)
        ->assertSee('Hal')
        ->assertDontSee('Balie')
        ->set('placeId', $unit->id)
        ->call('issue');

    $screen = Screen::query()->where('unit_id', $unit->id)->first();
    expect($screen)->not->toBeNull()
        ->and($screen->pairing_code)->not->toBeNull();

    Livewire::actingAs($admin)
        ->test(AddDevice::class)
        ->set('deviceType', 'screen')
        ->set('locationId', $location->id)
        ->set('placeId', $other->id)
        ->call('issue')
        ->assertNotFound();

    Livewire::actingAs($admin)
        ->test(AddDevice::class)
        ->set('deviceType', 'camera')
        ->set('locationId', $location->id)
        ->set('placeId', $unit->id)
        ->call('issue');

    expect(Camera::query()->where('unit_id', $unit->id)->first()?->pairing_code)->not->toBeNull();
});

it('laat een medewerker geen code maken', function () {
    $tenant = addDeviceTenant();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);
    $point = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($employee)
        ->test(AddDevice::class)
        ->set('deviceType', 'clock')
        ->set('placeId', $point->id)
        ->call('issue')
        ->assertForbidden();
});
