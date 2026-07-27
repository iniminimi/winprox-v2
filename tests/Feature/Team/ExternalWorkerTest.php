<?php

use App\Actions\Team\CreateWorkerAction;
use App\Actions\Team\UpdateWorkerAction;
use App\Livewire\Pages\Team;
use App\Models\InternalTeam;
use App\Models\Worker;
use Livewire\Livewire;

it('zet is_external automatisch bij company_name bij aanmaken', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $worker = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Erik',
        'last_name' => 'Peeters',
        'company_name' => 'Elektro Peeters',
        'is_external' => false,
    ], (int) $admin->id);

    expect($worker->is_external)->toBeTrue()
        ->and($worker->company_name)->toBe('Elektro Peeters');
});

it('laat zzp-extern toe zonder bedrijfsnaam', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $worker = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Anna',
        'last_name' => 'Janssen',
        'is_external' => true,
        'company_name' => null,
    ], (int) $admin->id);

    expect($worker->is_external)->toBeTrue()
        ->and($worker->company_name)->toBeNull();
});

it('trimt lege company_name naar null en houdt is_external-toggle', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $updated = app(UpdateWorkerAction::class)->handle($worker, [
        'first_name' => $worker->first_name,
        'last_name' => $worker->last_name,
        'is_external' => true,
        'company_name' => '   ',
    ], (int) $admin->id);

    expect($updated->is_external)->toBeTrue()
        ->and($updated->company_name)->toBeNull();
});

it('toont externe badge en bedrijf in team-hub', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->external('Elektro Peeters')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Erik',
        'last_name' => 'Peeters',
    ]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('toggleTeam', $team->id)
        ->assertSee('Elektro Peeters')
        ->assertSee(__('team.workers.external_badge'));
});

it('slaat externe worker op via team-hub met auto-flag', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openAddWorker', $team->id)
        ->set('workerFirstName', 'Sven')
        ->set('workerLastName', 'Peeters')
        ->set('workerIsExternal', true)
        ->set('workerCompanyName', 'Schilders BV')
        ->call('saveWorker')
        ->assertHasNoErrors();

    $worker = Worker::where('first_name', 'Sven')->first();
    expect($worker)->not->toBeNull()
        ->and($worker->is_external)->toBeTrue()
        ->and($worker->company_name)->toBe('Schilders BV');
});

it('wist bedrijfsnaam wanneer externe-checkbox uitgaat', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openAddWorker', $team->id)
        ->set('workerIsExternal', true)
        ->set('workerCompanyName', 'Schilders BV')
        ->set('workerIsExternal', false)
        ->assertSet('workerCompanyName', '');
});
