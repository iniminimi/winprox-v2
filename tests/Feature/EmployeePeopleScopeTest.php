<?php

use App\Actions\Search\SearchTenantGlobalAction;
use App\Livewire\Pages\Team;
use App\Livewire\Pages\WorkersIndex;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('toont een medewerker alleen uitvoerders en collega’s van haar locatie', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'name' => 'Beheerder']);
    $here = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Magazijn A']);
    $away = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Magazijn B']);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id, 'name' => 'Eva Hier']);
    $employee->locations()->sync([$here->id]);
    $colleague = User::factory()->employee()->create(['tenant_id' => $tenant->id, 'name' => 'Mila Hier']);
    $colleague->locations()->sync([$here->id]);
    $other = User::factory()->employee()->create(['tenant_id' => $tenant->id, 'name' => 'Otto Daar']);
    $other->locations()->sync([$away->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Schoonmaak']);
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
    $unassigned = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Ada',
        'last_name' => 'Overal',
    ]);

    expect(Gate::forUser($employee)->allows('view', $workerHere))->toBeTrue()
        ->and(Gate::forUser($employee)->allows('update', $workerHere))->toBeTrue()
        ->and(Gate::forUser($employee)->allows('view', $workerAway))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('view', $unassigned))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('view', $colleague))->toBeTrue()
        ->and(Gate::forUser($employee)->allows('view', $other))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('update', $colleague))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('view', $workerAway))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('view', $other))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $colleague))->toBeTrue();

    Livewire::actingAs($employee)
        ->test(WorkersIndex::class)
        ->assertSee('Nora Hier')
        ->assertDontSee('Zora Daar')
        ->assertDontSee('Ada Overal');

    Livewire::actingAs($admin)
        ->test(WorkersIndex::class)
        ->assertSee('Nora Hier')
        ->assertSee('Zora Daar')
        ->assertSee('Ada Overal');

    $this->actingAs($employee)
        ->get(route('team.show', $team))
        ->assertOk()
        ->assertSee('Nora Hier')
        ->assertDontSee('Zora Daar')
        ->assertDontSee('Ada Overal');

    Livewire::actingAs($employee)
        ->test(Team::class)
        ->call('openEditWorker', $workerAway->id)
        ->assertForbidden();

    Livewire::withQueryParams(['section' => 'backoffice'])
        ->actingAs($employee)
        ->test(Team::class)
        ->assertSee('Mila Hier')
        ->assertSee('Eva Hier')
        ->assertDontSee('Otto Daar')
        ->assertDontSee('openEditColleague', false);

    Livewire::withQueryParams(['section' => 'backoffice'])
        ->actingAs($admin)
        ->test(Team::class)
        ->assertSee('Otto Daar')
        ->assertSee('openEditColleague', false);

    $found = app(SearchTenantGlobalAction::class)->handle($employee, $tenant->id, 'Zora');
    expect($found->get('workers'))->toBeNull();

    $foundHere = app(SearchTenantGlobalAction::class)->handle($employee, $tenant->id, 'Nora');
    expect($foundHere->get('workers')?->pluck('title')->all())->toBe(['Nora Hier']);
});

it('laat een medewerker zonder locatiebeperking iedereen zien', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Zora',
        'last_name' => 'Daar',
    ]);
    $worker->locations()->sync([$location->id]);

    expect($employee->accessibleLocationIds())->toBeNull()
        ->and(Gate::forUser($employee)->allows('view', $worker))->toBeTrue();

    Livewire::actingAs($employee)
        ->test(WorkersIndex::class)
        ->assertSee('Zora Daar');
});

it('bewaart locaties buiten het bereik wanneer een medewerker een uitvoerder opslaat', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $here = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Magazijn A']);
    $away = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Magazijn B']);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    $employee->locations()->sync([$here->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Nora',
        'last_name' => 'Beide',
    ]);
    $worker->locations()->sync([$here->id, $away->id]);

    Livewire::actingAs($employee)
        ->test(Team::class)
        ->call('openEditWorker', $worker->id)
        ->set('selectedWorkerLocationIds', [$here->id])
        ->call('saveWorker')
        ->assertHasNoErrors();

    expect($worker->fresh()->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->sort()->values()->all())
        ->toBe([(int) $here->id, (int) $away->id]);
});
