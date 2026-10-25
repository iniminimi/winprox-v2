<?php

use App\Actions\Time\ListLocationStaffingShortfallsAction;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Livewire\Time\StaffingIndex;
use App\Livewire\Time\RosterIndex;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function staffingTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $admin, $team];
}

function staffingType(Tenant $tenant, string $code, ?ShiftTypeKind $kind = null, bool $active = true): ShiftType
{
    return ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => $code,
        'kind' => $kind ?? ShiftTypeKind::Work,
        'is_active' => $active,
    ]);
}

function staffingWorker(Tenant $tenant, InternalTeam $team, array $locationIds = []): Worker
{
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    if ($locationIds !== []) {
        $worker->locations()->sync($locationIds);
    }

    return $worker;
}

function staffingShift(Tenant $tenant, Worker $worker, ShiftType $type, string $date, ?int $locationId = null, ?string $status = null): PlannedShift
{
    return PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'shift_type_id' => $type->id,
        'kind' => $type->kind ?? ShiftTypeKind::Work,
        'location_id' => $locationId,
        'status' => $status ?? PlannedShiftStatus::Draft,
    ]);
}

it('toont een zichtbare dag zonder werkdiensten als 0/N tekort', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $type = staffingType($tenant, 'V2');
    $location->update(['min_staffing' => [(string) $type->id => 1]]);

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [Carbon::today()->toDateString()],
    );

    expect($result['total'])->toBe(1)
        ->and($result['locations'][0]['items'][0])
        ->toMatchArray(['code' => 'V2', 'actual' => 0, 'required' => 1]);
});

it('negeert verleden en niet-zichtbare dagen', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $type = staffingType($tenant, 'D');
    $location->update(['min_staffing' => [(string) $type->id => 2]]);

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [Carbon::today()->subDay()->toDateString(), Carbon::today()->addDays(3)->toDateString()],
    );

    expect($result['total'])->toBe(1)
        ->and($result['locations'][0]['items'][0]['date'])->toBe(Carbon::today()->addDays(3)->toDateString());
});

it('dekt een vereiste met een dienst van een ander team (tenant-brede telling)', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $otherTeam = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $type = staffingType($tenant, 'D');
    $location->update(['min_staffing' => [(string) $type->id => 1]]);
    $worker = staffingWorker($tenant, $otherTeam, [$location->id]);
    staffingShift($tenant, $worker, $type, Carbon::today()->toDateString());

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [Carbon::today()->toDateString()],
    );

    expect($result['total'])->toBe(0)->and($result['locations'])->toBe([]);
});

it('telt elke uitvoerder maar één keer bij split-shifts', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $type = staffingType($tenant, 'D');
    $location->update(['min_staffing' => [(string) $type->id => 2]]);
    $worker = staffingWorker($tenant, $team, [$location->id]);
    staffingShift($tenant, $worker, $type, Carbon::today()->toDateString());
    staffingShift($tenant, $worker, $type, Carbon::today()->toDateString());

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [Carbon::today()->toDateString()],
    );

    expect($result['total'])->toBe(1)
        ->and($result['locations'][0]['items'][0]['actual'])->toBe(1)
        ->and($result['locations'][0]['items'][0]['required'])->toBe(2);
});

it('telt diensten met expliciete locatie; ambigue uitvoerders nergens', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $siteA = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'A']);
    $siteB = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'B']);
    $type = staffingType($tenant, 'L');
    $siteA->update(['min_staffing' => [(string) $type->id => 2]]);
    $siteB->update(['min_staffing' => [(string) $type->id => 1]]);

    $today = Carbon::today()->toDateString();

    // Uitvoerder met twee locaties + dienst zonder location_id → telt nergens.
    $ambiguous = staffingWorker($tenant, $team, [$siteA->id, $siteB->id]);
    staffingShift($tenant, $ambiguous, $type, $today);

    // Dienst met expliciete location_id → telt bij die locatie, ook voor invaller.
    $floater = staffingWorker($tenant, $team);
    staffingShift($tenant, $floater, $type, $today, locationId: $siteA->id);

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [$today],
    );

    expect($result['total'])->toBe(2);
    $a = collect($result['locations'])->firstWhere('name', 'A');
    $b = collect($result['locations'])->firstWhere('name', 'B');
    expect($a['items'][0]['actual'])->toBe(1)->and($a['items'][0]['required'])->toBe(2)
        ->and($b['items'][0]['actual'])->toBe(0)->and($b['items'][0]['required'])->toBe(1);
});

it('telt diensten zonder locatie op de enige toegewezen locatie', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $type = staffingType($tenant, 'R');
    $location->update(['min_staffing' => [(string) $type->id => 1]]);
    $worker = staffingWorker($tenant, $team, [$location->id]);
    staffingShift($tenant, $worker, $type, Carbon::today()->toDateString());

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [Carbon::today()->toDateString()],
    );

    expect($result['total'])->toBe(0);
});

it('negeert inactieve en verwijderde diensttypes in de config', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $inactive = staffingType($tenant, 'X1', active: false);
    $absence = staffingType($tenant, 'Z', kind: ShiftTypeKind::Sick);
    $deletedId = 999999;
    $location->update(['min_staffing' => [
        (string) $inactive->id => 2,
        (string) $absence->id => 1,
        (string) $deletedId => 3,
    ]]);

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [Carbon::today()->toDateString()],
    );

    expect($result['total'])->toBe(0)->and($result['locations'])->toBe([]);
});

it('beperkt de lijst tot vijf tekorten en sorteert deterministisch', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $siteB = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bravo']);
    $siteA = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alpha']);

    // 2 locaties × 3 types = 6 tekorten op dezelfde dag.
    $reqs = [];
    foreach (['D', 'L', 'V'] as $code) {
        $reqs[(string) staffingType($tenant, $code)->id] = 1;
    }
    $siteA->update(['min_staffing' => $reqs]);
    $siteB->update(['min_staffing' => $reqs]);

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [Carbon::today()->toDateString()],
    );

    expect($result['total'])->toBe(6)
        ->and($result['locations'])->toHaveCount(2)
        ->and($result['locations'][0]['name'])->toBe('Alpha')
        ->and($result['locations'][0]['items'])->toHaveCount(3)
        ->and(array_column($result['locations'][0]['items'], 'code'))->toBe(['D', 'L', 'V'])
        ->and($result['locations'][1]['name'])->toBe('Bravo');
});

it('sorteert datum vóór locatie vóór diensttype', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $site = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Zeta']);
    $typeB = staffingType($tenant, 'B');
    $typeA = staffingType($tenant, 'A');
    $site->update(['min_staffing' => [(string) $typeA->id => 1, (string) $typeB->id => 1]]);

    $day1 = Carbon::today()->toDateString();
    $day2 = Carbon::today()->addDay()->toDateString();

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [$day2, $day1],
    );

    $items = $result['locations'][0]['items'];
    expect(array_map(fn ($i) => $i['date'].'|'.$i['code'], array_slice($items, 0, 4)))
        ->toBe(["{$day1}|A", "{$day1}|B", "{$day2}|A", "{$day2}|B"]);
});

it('beperkt weergegeven locaties tot actor-toegang en locatiefilter', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $allowed = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Zichtbaar']);
    $hidden = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Verborgen']);
    $type = staffingType($tenant, 'D');
    $allowed->update(['min_staffing' => [(string) $type->id => 1]]);
    $hidden->update(['min_staffing' => [(string) $type->id => 1]]);
    $today = Carbon::today()->toDateString();

    $scoped = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [$today],
        actorLocationIds: [$allowed->id],
    );
    expect($scoped['total'])->toBe(1)
        ->and($scoped['locations'][0]['name'])->toBe('Zichtbaar');

    $filtered = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [$today],
        activeLocationId: $hidden->id,
    );
    expect($filtered['total'])->toBe(1)
        ->and($filtered['locations'][0]['name'])->toBe('Verborgen');
});

it('telt drafts én gepubliceerde diensten, maar geen afwezigheden', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $type = staffingType($tenant, 'D');
    $sick = staffingType($tenant, 'ZK', kind: ShiftTypeKind::Sick);
    $location->update(['min_staffing' => [(string) $type->id => 2]]);
    $today = Carbon::today()->toDateString();

    staffingShift($tenant, staffingWorker($tenant, $team, [$location->id]), $type, $today);
    staffingShift($tenant, staffingWorker($tenant, $team, [$location->id]), $type, $today, status: PlannedShiftStatus::Published->value);
    staffingShift($tenant, staffingWorker($tenant, $team, [$location->id]), $sick, $today);

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [$today],
    );

    expect($result['total'])->toBe(0);
});

it('toont het indicatieblok in het uurrooster', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $type = staffingType($tenant, 'V2');
    $location->update(['min_staffing' => [(string) $type->id => 1]]);
    staffingWorker($tenant, $team, [$location->id]);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->set('locationFilter', $location->id)
        ->call('nextWeek')
        ->assertSee(__('time.schedule.staffing.title'), false)
        ->assertSee(__('time.schedule.staffing.missing', ['code' => 'V2', 'actual' => 0, 'required' => 1]), false);
});

it('toont geen indicatieblok zonder vereisten', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    staffingWorker($tenant, $team, [$location->id]);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->set('locationFilter', $location->id)
        ->assertDontSee(__('time.schedule.staffing.title'), false);
});

it('slaat minimumbezetting op via de staffing-pagina', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kapel']);
    $type = staffingType($tenant, 'D');

    Livewire::actingAs($admin)
        ->test(StaffingIndex::class)
        ->call('addStaffingRow', $location->id)
        ->set("staffingRows.{$location->id}.0.type_id", (string) $type->id)
        ->set("staffingRows.{$location->id}.0.min", '3')
        ->call('saveLocation', $location->id)
        ->assertHasNoErrors();

    expect($location->fresh()->min_staffing)->toBe([(string) $type->id => 3]);
});

it('weigert onbekende diensttypes op de staffing-pagina', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kapel']);

    Livewire::actingAs($admin)
        ->test(StaffingIndex::class)
        ->call('addStaffingRow', $location->id)
        ->set("staffingRows.{$location->id}.0.type_id", '424242')
        ->set("staffingRows.{$location->id}.0.min", '1')
        ->call('saveLocation', $location->id)
        ->assertHasErrors('staffingRows');

    expect($location->fresh()->min_staffing)->toBeNull();
});

it('behoudt verouderde regels bij opslaan tot de beheerder ze verwijdert', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kapel']);
    $inactive = staffingType($tenant, 'X1', active: false);
    $location->update(['min_staffing' => [(string) $inactive->id => 2, '999999' => 3]]);

    Livewire::actingAs($admin)
        ->test(StaffingIndex::class)
        ->call('saveLocation', $location->id)
        ->assertHasNoErrors();

    expect($location->fresh()->min_staffing)
        ->toBe([(string) $inactive->id => 2, '999999' => 3]);
});

it('verwijdert een verouderde regel enkel expliciet via de rij-verwijdering', function () {
    [$tenant, $admin, $team] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kapel']);
    $inactive = staffingType($tenant, 'X1', active: false);
    $location->update(['min_staffing' => [(string) $inactive->id => 2]]);

    Livewire::actingAs($admin)
        ->test(StaffingIndex::class)
        ->call('removeStaffingRow', $location->id, 0)
        ->call('saveLocation', $location->id)
        ->assertHasNoErrors();

    expect($location->fresh()->min_staffing)->toBeNull();
});

it('houdt tenants strikt gescheiden', function () {
    [$tenant, $admin, $team] = staffingTenant();
    [$otherTenant] = staffingTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $otherLocation = Location::factory()->create(['tenant_id' => $otherTenant->id]);
    $type = staffingType($tenant, 'D');
    $otherType = staffingType($otherTenant, 'D');
    $location->update(['min_staffing' => [(string) $type->id => 1]]);
    $otherLocation->update(['min_staffing' => [(string) $otherType->id => 1]]);
    $today = Carbon::today()->toDateString();

    $result = app(ListLocationStaffingShortfallsAction::class)->handle(
        (int) $tenant->id,
        [$today],
    );

    expect($result['total'])->toBe(1)
        ->and($result['locations'][0]['name'])->toBe((string) $location->name);
});
