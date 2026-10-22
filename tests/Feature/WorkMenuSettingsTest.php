<?php

use App\Actions\Team\UpdateTenantWorkMenuAction;
use App\Enums\TaskStatus;
use App\Actions\Units\ImportUnitsAction;
use App\Data\Units\ImportUnitsData;
use App\Livewire\Locations\Index as LocationsIndex;
use App\Livewire\Locations\Show;
use App\Livewire\Pages\Settings;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitCheckList;
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
        ->call('saveWorkMenuSettings')
        ->assertRedirect(route('settings.index'));

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
        'work_menu_checklists_enabled' => false,
        'work_menu_unit_checks_enabled' => false,
        'work_menu_issues_tasks_enabled' => false,
        'work_menu_unit_measurements_enabled' => false,
    ], $admin->id);

    $fresh = $tenant->fresh();
    expect($fresh->workMenuCalendarEnabled())->toBeFalse()
        ->and($fresh->workMenuReservationsEnabled())->toBeFalse()
        ->and($fresh->workMenuChecklistsEnabled())->toBeFalse()
        ->and($fresh->workMenuUnitChecksEnabled())->toBeFalse()
        ->and($fresh->workMenuIssuesTasksEnabled())->toBeFalse()
        ->and($fresh->workMenuUnitMeasurementsEnabled())->toBeFalse();

    // Opgeslagen vlag blijft true, maar de getter volgt de hoofdschakelaar.
    expect($fresh->work_menu_inspection_rounds_enabled)->toBeTrue()
        ->and($fresh->workMenuInspectionRoundsEnabled())->toBeFalse();

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

it('verbergt checklists in de sidebar en blokkeert de route wanneer uitgeschakeld', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_checklists_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('href="'.route('checklists.index').'"', false);

    $this->actingAs($admin)
        ->get(route('checklists.index'))
        ->assertForbidden();
});

it('verbergt meldingen en taken en blokkeert hun routes wanneer uitgeschakeld', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_issues_tasks_enabled' => false,
        'work_menu_inspection_rounds_enabled' => false,
    ]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'approved_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('href="'.route('issues.index').'"', false)
        ->assertDontSee('href="'.route('tasks.index').'"', false)
        ->assertDontSeeHtml('<h2 class="wp-section-title">'.e(__('dashboard.recent.title')).'</h2>');

    $this->actingAs($admin)->get(route('issues.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('tasks.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('issues.show', $issue))->assertForbidden();
});

it('toont meldingen en taken zodra de schakelaar aan staat ook als alles gesloten is', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_issues_tasks_enabled' => true,
    ]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'status' => TaskStatus::Closed,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('href="'.route('issues.index').'"', false)
        ->assertSee('href="'.route('tasks.index').'"', false)
        ->assertSeeHtml('<h2 class="wp-section-title">'.e(__('dashboard.recent.title')).'</h2>');
});

it('verbergt de hele Werk-groep wanneer geen enkel werkmenu-item zichtbaar is', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_issues_tasks_enabled' => false,
        'work_menu_inspection_rounds_enabled' => false,
        'work_menu_checklists_enabled' => false,
        'work_menu_unit_checks_enabled' => false,
        'work_menu_calendar_enabled' => false,
        'work_menu_reservations_enabled' => false,
        'work_menu_unit_measurements_enabled' => false,
        'has_time_module' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSeeHtml('>Werk</span>');

    // Kalender terug aan terwijl de hoofdschakelaar uit staat: Werk blijft weg.
    $tenant->update(['work_menu_calendar_enabled' => true]);
    $admin->refresh();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSeeHtml('>Werk</span>');

    // Pas de hoofdschakelaar aan: de opgeslagen kalender-vlag komt mee terug.
    $tenant->update(['work_menu_issues_tasks_enabled' => true]);
    $admin->refresh();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeHtml('>Werk</span>');
});

it('schakelt alle werkmenu-items mee uit wanneer meldingen en taken uit staan', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_issues_tasks_enabled' => false,
        'work_menu_inspection_rounds_enabled' => true,
        'work_menu_reservations_enabled' => true,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->get(route('issues.index'))->assertForbidden();

    $this->actingAs($admin)
        ->get(route('issues.index', ['inspection_round' => 1]))
        ->assertForbidden();

    expect($tenant->workMenuInspectionRoundsEnabled())->toBeFalse()
        ->and($tenant->workMenuReservationsEnabled())->toBeFalse()
        ->and($tenant->workMenuUnitChecksEnabled())->toBeFalse()
        ->and($tenant->workMenuCalendarEnabled())->toBeFalse()
        ->and($tenant->workMenuUnitMeasurementsEnabled())->toBeFalse()
        ->and($tenant->workMenuChecklistsEnabled())->toBeFalse();
});

it('verbergt de onderliggende werkmenu-opties als de hoofdschakelaar uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_issues_tasks_enabled' => false,
        'work_menu_reservations_enabled' => true,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSee('wire:model.live="workMenuIssuesTasksEnabled"', false)
        ->assertDontSee('wire:model="workMenuInspectionRoundsEnabled"', false)
        ->assertDontSee('wire:model="workMenuChecklistsEnabled"', false)
        ->assertDontSee('wire:model="workMenuUnitChecksEnabled"', false)
        ->assertDontSee('wire:model="workMenuCalendarEnabled"', false)
        ->assertDontSee('wire:model="workMenuReservationsEnabled"', false)
        ->assertDontSee('wire:model="workMenuUnitMeasurementsEnabled"', false)
        ->set('workMenuIssuesTasksEnabled', true)
        ->assertSee('wire:model="workMenuInspectionRoundsEnabled"', false)
        ->assertSee('wire:model="workMenuReservationsEnabled"', false)
        ->set('workMenuIssuesTasksEnabled', false)
        ->call('saveWorkMenuSettings')
        ->assertRedirect(route('settings.index'));

    // Verborgen opties worden niet gereset: opgeslagen vlag blijft bewaard.
    expect($tenant->fresh()->work_menu_reservations_enabled)->toBeTrue();
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

it('weigert een nieuwe checklist-koppeling aan een unit wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_checklists_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $checkList = UnitCheckList::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'allow_unit_checks' => true,
        'unit_check_list_id' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->set('unitCheckListId', $checkList->id)
        ->call('saveUnit')
        ->assertHasErrors(['unitCheckListId']);

    expect($unit->fresh()->unit_check_list_id)->toBeNull();
});

it('laat een gekoppelde checklist gewijzigd of losgekoppeld wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_checklists_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $checkList = UnitCheckList::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'allow_unit_checks' => true,
        'unit_check_list_id' => $checkList->id,
        'name' => 'Machine A',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->set('unitName', 'Machine A1')
        ->call('saveUnit')
        ->assertHasNoErrors();

    expect($unit->fresh()->name)->toBe('Machine A1')
        ->and($unit->fresh()->unit_check_list_id)->toBe($checkList->id);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->set('unitCheckListId', null)
        ->call('saveUnit')
        ->assertHasNoErrors();

    expect($unit->fresh()->unit_check_list_id)->toBeNull();
});

it('verbergt unit checks in de sidebar en blokkeert de route wanneer uitgeschakeld', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_unit_checks_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('href="'.route('unit-checks.index').'"', false);

    $this->actingAs($admin)
        ->get(route('unit-checks.index'))
        ->assertForbidden();
});

it('blokkeert QR-checks op een unit die het aan had wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_unit_checks_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => null,
        'allow_unit_checks' => true,
    ]);

    expect($unit->fresh()->allowsUnitChecks())->toBeFalse();
});

it('weigert nieuw inschakelen van unit checks op een unit wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_unit_checks_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'allow_unit_checks' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->set('unitAllowUnitChecks', true)
        ->call('saveUnit')
        ->assertHasErrors(['unitAllowUnitChecks']);

    expect($unit->fresh()->allow_unit_checks)->toBeFalse();
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
        ->assertDontSee('wire:submit="saveWorkMenuSettings"', false);
});

it('weigert nieuw inschakelen van bezoekersmeldingen op een unit wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_issues_tasks_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'public_reports_enabled' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->set('unitPublicReportsEnabled', true)
        ->call('saveUnit')
        ->assertHasErrors(['unitPublicReportsEnabled']);

    expect($unit->fresh()->public_reports_enabled)->toBeFalse();
});

it('weigert bezoekersmeldingen in bulk-aanmaak wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_issues_tasks_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openBulkModal')
        ->set('bulkRanges', [['start' => '1', 'count' => 2, 'padding' => '', 'prefix' => '', 'suffix' => '']])
        ->set('bulkPublicReportsEnabled', true)
        ->call('createBulk')
        ->assertHasErrors(['bulkPublicReportsEnabled']);

    expect(Unit::query()->where('location_id', $location->id)->count())->toBe(0);
});

it('weigert nieuw inschakelen van vorige meldingen op een categorie wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_issues_tasks_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kranen',
        'show_previous_issues' => false,
    ]);
    $category->teams()->sync([$team->id]);

    Livewire::actingAs($admin)
        ->test(LocationsIndex::class)
        ->call('openEditCategory', $category->id)
        ->set('categoryShowPreviousIssues', true)
        ->call('saveCategory')
        ->assertHasErrors(['categoryShowPreviousIssues']);

    expect($category->fresh()->show_previous_issues)->toBeFalse();
});

it('laat het uitschakelen van bezoekersmeldingen op een unit toe wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_issues_tasks_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'public_reports_enabled' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->set('unitPublicReportsEnabled', false)
        ->call('saveUnit')
        ->assertHasNoErrors();

    expect($unit->fresh()->public_reports_enabled)->toBeFalse();
});

it('weigert csv-import met public_reports_enabled wanneer werkmenu uit staat', function () {
    $tenant = Tenant::factory()->create([
        'work_menu_issues_tasks_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $csvContent = "unit_name,public_reports_enabled\n";
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
        ->and($result['errors'][0])->toContain(__('settings.work_menu.errors.issues_tasks_disabled'));
});
