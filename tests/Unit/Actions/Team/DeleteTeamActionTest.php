<?php

use App\Actions\Team\DeleteTeamAction;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('verwijdert een team zonder uitvoerders', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Fout team']);

    app(DeleteTeamAction::class)->handle($team, $admin->id);

    expect(InternalTeam::find($team->id))->toBeNull();
});

it('weigert het verwijderen van een team met uitvoerders', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);

    app(DeleteTeamAction::class)->handle($team);
})->throws(InvalidArgumentException::class, 'team_has_workers');
