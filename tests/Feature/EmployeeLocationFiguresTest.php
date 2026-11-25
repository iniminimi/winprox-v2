<?php

use App\Actions\Dashboard\BuildDashboardStatsAction;
use App\Actions\Search\SearchTenantGlobalAction;
use App\Actions\Time\ClockInAction;
use App\Enums\TaskStatus;
use App\Livewire\Time\AbsenceRequestsIndex;
use App\Livewire\Time\AlarmsIndex;
use App\Livewire\Time\OvertimeIndex;
use App\Models\AbsenceRequest;
use App\Models\AbsenceRequestPhoto;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('beperkt overuren, alarmen, verlof, cijfers en zoeken tot de locaties van de medewerker', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    config(['time.long_shift_hours' => 8]);

    $here = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Magazijn A']);
    $away = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Magazijn B']);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    $employee->locations()->sync([$here->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $workerHere = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Nora',
        'last_name' => 'Hier',
    ]);
    $workerHere->locations()->sync([$here->id]);
    $workerAway = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Zora',
        'last_name' => 'Daar',
    ]);
    $workerAway->locations()->sync([$away->id]);

    $pointHere = ClockPoint::factory()->forLocation($here)->create(['name' => 'Punt Hier']);
    $pointAway = ClockPoint::factory()->forLocation($away)->create(['name' => 'Punt Daar']);
    $shiftHere = app(ClockInAction::class)->handle($workerHere, $pointHere);
    $shiftHere->update(['clock_in_at' => now()->subHours(9)]);
    $shiftAway = app(ClockInAction::class)->handle($workerAway, $pointAway);
    $shiftAway->update(['clock_in_at' => now()->subHours(9)]);

    $requestHere = AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $workerHere->id,
        'description' => 'Verlof Hier',
    ]);
    $requestAway = AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $workerAway->id,
        'description' => 'Verlof Daar',
    ]);
    $photoAway = AbsenceRequestPhoto::query()->create([
        'tenant_id' => $tenant->id,
        'absence_request_id' => $requestAway->id,
        'path' => 'absence-request-photos/'.$tenant->id.'/weg.jpg',
    ]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $here->id,
        'description' => 'Lek Hier',
        'status' => TaskStatus::New,
    ]);
    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $away->id,
        'description' => 'Lek Daar',
        'status' => TaskStatus::New,
    ]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $away->id,
        'name' => 'Unit Daar',
        'is_active' => true,
    ]);

    expect(Gate::forUser($employee)->allows('view', $requestHere))->toBeTrue()
        ->and(Gate::forUser($employee)->allows('view', $requestAway))->toBeFalse();

    $this->actingAs($employee)
        ->get(route('absence-photos.file', $photoAway))
        ->assertForbidden();

    Livewire::actingAs($employee)
        ->test(OvertimeIndex::class)
        ->assertSee('Nora Hier')
        ->assertDontSee('Zora Daar')
        ->assertSee('Punt Hier')
        ->assertDontSee('Punt Daar');

    Livewire::actingAs($employee)
        ->test(AlarmsIndex::class)
        ->assertSee('Nora Hier')
        ->assertDontSee('Zora Daar')
        ->assertSee('Magazijn A')
        ->assertDontSee('Magazijn B');

    Livewire::actingAs($employee)
        ->test(AbsenceRequestsIndex::class)
        ->assertSee('Nora Hier')
        ->assertDontSee('Zora Daar')
        ->assertSee('Verlof Hier')
        ->assertDontSee('Verlof Daar')
        ->assertSee('Punt Hier')
        ->assertDontSee('Punt Daar');

    $stats = app(BuildDashboardStatsAction::class)->handle($tenant->id, true, false, true, [$here->id]);
    expect($stats->newIssues)->toBe(1)
        ->and($stats->locations)->toBe(1)
        ->and($stats->timeAttention)->toBe(1)
        ->and($stats->pendingAbsenceRequests)->toBe(1);

    $found = app(SearchTenantGlobalAction::class)->handle($employee, $tenant->id, 'Daar');
    expect($found->get('locations'))->toBeNull()
        ->and($found->get('units'))->toBeNull()
        ->and($found->get('issues'))->toBeNull();

    $foundHere = app(SearchTenantGlobalAction::class)->handle($employee, $tenant->id, 'Magazijn');
    expect($foundHere->get('locations')?->pluck('title')->all())->toBe(['Magazijn A']);

    $foundIssue = app(SearchTenantGlobalAction::class)->handle($employee, $tenant->id, 'Lek');
    expect($foundIssue->get('issues')?->pluck('title')->filter(fn (string $title) => str_contains($title, 'Lek Hier'))->isNotEmpty())->toBeTrue()
        ->and($foundIssue->get('issues')?->pluck('title')->filter(fn (string $title) => str_contains($title, 'Lek Daar'))->isEmpty())->toBeTrue();
});
