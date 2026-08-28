<?php

use App\Actions\Team\UpdateTenantWorkMenuAction;
use App\Actions\Units\ImportUnitsAction;
use App\Data\Units\ImportUnitsData;
use App\Livewire\Locations\Show;
use App\Livewire\Pages\Settings;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('laat een admin werkmenu-vlaggen opslaan via instellingen', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSee(__('settings.work_menu.title'), false)
        ->set('workMenuReservationsEnabled', false)
        ->assertSet('workMenuReservationsEnabled', false);

    expect($tenant->fresh()->workMenuReservationsEnabled())->toBeFalse();
});

it('persisteert werkmenu-wijzigingen via de action met audit', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    app(UpdateTenantWorkMenuAction::class)->handle($tenant, [
        'work_menu_calendar_enabled' => false,
        'work_menu_reservations_enabled' => false,
        'work_menu_inspection_rounds_enabled' => true,
        'work_menu_unit_measurements_enabled' => false,
    ], $admin->id);

    $fresh = $tenant->fresh();
    expect($fresh->workMenuCalendarEnabled())->toBeFalse()
        ->and($fresh->workMenuReservationsEnabled())->toBeFalse()
        ->and($fresh->workMenuInspectionRoundsEnabled())->toBeTrue()
        ->and($fresh->workMenuUnitMeasurementsEnabled())->toBeFalse();

    expect(\DB::table('audit_logs')->where('action', 'tenant.work_menu_updated')->exists())->toBeTrue();
});

it('verbergt reserveringen in de sidebar en blokkeert de route wanneer uitgeschakeld', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_reservations_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('href="'.route('reservations.index').'"', false);

    $this->actingAs($admin)
        ->get(route('reservations.index'))
        ->assertForbidden();
});

it('weigert nieuw inschakelen van reserveringen op een unit wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_reservations_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'allow_reservations' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->set('unitAllowReservations', true)
        ->call('saveUnit')
        ->assertHasErrors(['unitAllowReservations']);

    expect($unit->fresh()->allow_reservations)->toBeFalse();
});

it('laat grandfathered reserveringen op een unit toe wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_reservations_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'allow_reservations' => true,
        'name' => 'Vergaderzaal',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->set('unitName', 'Vergaderzaal A')
        ->call('saveUnit')
        ->assertHasNoErrors();

    expect($unit->fresh()->name)->toBe('Vergaderzaal A')
        ->and($unit->fresh()->allow_reservations)->toBeTrue();
});

it('weigert csv-import met allow_reservations wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_reservations_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "unit_name,allow_reservations\n";
    $csvContent .= "Imported Unit,yes\n";
    $file = UploadedFile::fake()->createWithContent('units.csv', $csvContent);

    $result = app(ImportUnitsAction::class)->handle(
        new ImportUnitsData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            locationId: $location->id,
        ),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeFalse()
        ->and($result['errors'][0])->toContain(__('settings.work_menu.errors.reservations_disabled'));
});

it('blokkeert inspectierondes deep-link wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_inspection_rounds_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('issues.index', ['inspection_round' => 1]))
        ->assertForbidden();
});

it('toont werkmenu-instellingen niet aan medewerkers', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($employee)
        ->test(Settings::class)
        ->assertDontSee('wire:model.live="workMenuCalendarEnabled"', false);
});
