<?php

declare(strict_types=1);

use App\Actions\Units\SaveUnitCheckListAction;
use App\Data\Units\SaveUnitCheckListData;
use App\Enums\TaskStatus;
use App\Enums\UnitCheckResult;
use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitCheck;
use App\Models\UnitCheckList;
use App\Models\UnitCheckListItem;
use App\Models\UnitCheckListTranslation;
use App\Models\Worker;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{tenant: Tenant, location: Location, team: InternalTeam, unit: Unit}
 */
function unitCheckListPortalScaffold(): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Test Category',
        'allow_unit_checks' => true,
    ]);
    $category->teams()->sync([$team->id]);

    $unit = Unit::factory()->withQrToken('unit-check-list-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);

    return compact('tenant', 'location', 'team', 'unit');
}

it('shows checklist items on unit check and requires all for ok', function () {
    ['unit' => $unit, 'team' => $team, 'tenant' => $tenant] = unitCheckListPortalScaffold();

    $list = UnitCheckList::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Schoonmaak',
        'is_active' => true,
    ]);
    UnitCheckListItem::query()->create(['unit_check_list_id' => $list->id, 'label' => 'Vloer', 'sort_order' => 0]);
    UnitCheckListItem::query()->create(['unit_check_list_id' => $list->id, 'label' => 'WC', 'sort_order' => 1]);
    $unit->update(['unit_check_list_id' => $list->id]);

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-check-list-token'])
        ->call('openSection', 'unit_check')
        ->assertSee('Vloer')
        ->assertSee('WC')
        ->set('checkResult', 'ok')
        ->set('checkCheckedAt', now()->toIso8601String())
        ->set('checkChecklistItems', ['Vloer'])
        ->call('submitUnitCheck')
        ->assertHasErrors(['checkChecklistItems']);

    Livewire::test(UnitPortal::class, ['token' => 'unit-check-list-token'])
        ->call('openSection', 'unit_check')
        ->set('checkResult', 'ok')
        ->set('checkCheckedAt', now()->toIso8601String())
        ->set('checkChecklistItems', ['Vloer', 'WC'])
        ->call('submitUnitCheck')
        ->assertHasNoErrors()
        ->assertSet('portalSection', 'home');

    $check = UnitCheck::query()->latest('id')->first();
    expect($check->result)->toBe(UnitCheckResult::Ok)
        ->and($check->checklist_items)->toBe(['Vloer', 'WC']);
});

it('links and completes an open task on ok unit check', function () {
    ['unit' => $unit, 'team' => $team, 'tenant' => $tenant, 'location' => $location] = unitCheckListPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'is_recurring_cycle' => true,
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-check-list-token'])
        ->call('openSection', 'unit_check')
        ->set('checkResult', 'ok')
        ->set('checkCheckedAt', now()->toIso8601String())
        ->call('submitUnitCheck')
        ->assertHasNoErrors();

    expect(UnitCheck::query()->latest('id')->first()->task_id)->toBe($task->id)
        ->and($task->fresh()->status)->toBe(TaskStatus::Done);
});

it('saves a unit check list via action', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $list = app(SaveUnitCheckListAction::class)->handle(
        new SaveUnitCheckListData('Security ronde', ['Parking', 'Nooddeur A'], true),
        $tenant->id,
    );

    expect($list->name)->toBe('Security ronde')
        ->and($list->items)->toHaveCount(2)
        ->and($list->items->pluck('label')->all())->toBe(['Parking', 'Nooddeur A']);
});

it('creates a checklist from the teams page', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Pages\Team::class)
        ->call('openCreateCheckList')
        ->set('checkListName', 'Security ronde')
        ->set('checkListItemsText', "Parking\nNooddeur A")
        ->set('checkListIsActive', true)
        ->set('checkListTeamId', $team->id)
        ->call('saveCheckList')
        ->assertHasNoErrors()
        ->assertSet('showCheckListModal', false);

    $list = UnitCheckList::query()->where('name', 'Security ronde')->first();
    expect($list)->not->toBeNull()
        ->and($list->internal_team_id)->toBe($team->id)
        ->and($list->items)->toHaveCount(2);
});

it('copies a starter checklist', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Pages\Team::class)
        ->call('copyCheckListFromStarter', 'cleaning')
        ->assertHasNoErrors();

    $list = UnitCheckList::query()->where('internal_team_id', null)->latest('id')->first();
    expect($list)->not->toBeNull()
        ->and($list->items->count())->toBeGreaterThan(0);
});

it('copies the technical starter with five detailed points', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Pages\Team::class)
        ->call('copyCheckListFromStarter', 'technical')
        ->assertHasNoErrors();

    $list = UnitCheckList::query()->where('name', __('unit_checks.starters.technical.name'))->latest('id')->first();

    expect($list)->not->toBeNull()
        ->and($list->items)->toHaveCount(5)
        ->and($list->items->pluck('label')->all())->toBe([
            __('unit_checks.starters.technical.items.installations'),
            __('unit_checks.starters.technical.items.climate'),
            __('unit_checks.starters.technical.items.electrical'),
            __('unit_checks.starters.technical.items.safety_fire'),
            __('unit_checks.starters.technical.items.building'),
        ]);
});

it('deletes an unused checklist from the teams page', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $list = UnitCheckList::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tijdelijk',
        'is_active' => true,
    ]);
    UnitCheckListItem::query()->create([
        'unit_check_list_id' => $list->id,
        'label' => 'Punt A',
        'sort_order' => 0,
    ]);
    app(\App\Actions\Communication\EnsureUnitCheckListTranslationSlotsAction::class)->handle($list);

    expect(UnitCheckListTranslation::query()->where('unit_check_list_id', $list->id)->count())->toBeGreaterThan(0);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Pages\Team::class)
        ->call('deleteCheckList', $list->id)
        ->assertHasNoErrors();

    expect(UnitCheckList::query()->whereKey($list->id)->exists())->toBeFalse()
        ->and(UnitCheckListTranslation::query()->where('unit_check_list_id', $list->id)->count())->toBe(0)
        ->and(UnitCheckListItem::query()->where('unit_check_list_id', $list->id)->count())->toBe(0);
});

it('refuses to delete a checklist that is linked to a unit', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

    $list = UnitCheckList::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'In gebruik',
        'is_active' => true,
    ]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_check_list_id' => $list->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Pages\Team::class)
        ->call('deleteCheckList', $list->id)
        ->assertHasErrors(['checkListName']);

    expect(UnitCheckList::query()->whereKey($list->id)->exists())->toBeTrue();
});

it('shows and saves checklist translations in the edit modal', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);

    $list = UnitCheckList::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Schoonmaak',
        'original_language' => 'nl',
        'is_active' => true,
    ]);
    UnitCheckListItem::query()->create([
        'unit_check_list_id' => $list->id,
        'label' => 'Vloer',
        'sort_order' => 0,
    ]);
    UnitCheckListItem::query()->create([
        'unit_check_list_id' => $list->id,
        'label' => 'WC',
        'sort_order' => 1,
    ]);
    app(\App\Actions\Communication\EnsureUnitCheckListTranslationSlotsAction::class)->handle($list);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Pages\Team::class)
        ->call('openEditCheckList', $list->id)
        ->assertSet('showCheckListModal', true)
        ->set('checkListPreviewLocale', 'en')
        ->set('checkListTranslationName', 'Cleaning')
        ->set('checkListTranslationItemsText', "Floor\nToilet")
        ->call('saveCheckListTranslationOverride')
        ->assertHasNoErrors();

    $row = $list->translations()->where('locale', 'en')->first();
    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Cleaning')
        ->and($row->items)->toBe(['Floor', 'Toilet'])
        ->and($row->status->value)->toBe('completed');
});

it('filters unit checklist dropdown by category teams', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $teamA = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true, 'name' => 'Team A']);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true, 'name' => 'Team B']);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_checks' => true,
    ]);
    $category->teams()->sync([$teamA->id]);

    $shared = UnitCheckList::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Shared list',
        'is_active' => true,
        'internal_team_id' => null,
    ]);
    $forA = UnitCheckList::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Team A list',
        'is_active' => true,
        'internal_team_id' => $teamA->id,
    ]);
    $forB = UnitCheckList::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Team B list',
        'is_active' => true,
        'internal_team_id' => $teamB->id,
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Locations\Show::class, ['location' => $location])
        ->call('openCreateUnit')
        ->set('unitAllowUnitChecks', true)
        ->set('unitCategoryId', $category->id)
        ->assertSee('Shared list')
        ->assertSee('Team A list')
        ->assertDontSee('Team B list');

    expect($shared->id)->toBeInt()
        ->and($forA->id)->toBeInt()
        ->and($forB->id)->toBeInt();
});
