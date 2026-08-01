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
