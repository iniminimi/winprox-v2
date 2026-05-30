<?php

use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerIconGuard;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

function authTeam(): InternalTeam
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    return InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
}

it('vindt een worker op naam (found)', function () {
    $team = authTeam();
    Worker::factory()->withIcon('heart')->create([
        'tenant_id' => $team->tenant_id,
        'internal_team_id' => $team->id,
        'first_name' => 'Sven',
        'last_name' => 'Peeters',
    ]);

    $result = WorkerDeviceSession::resolveIdentityOnTeam($team, 'sven', 'PEETERS');

    expect($result['status'])->toBe('found')
        ->and($result['worker']->first_name)->toBe('Sven');
});

it('herkent een worker zonder icoon als claimable', function () {
    $team = authTeam();
    Worker::factory()->claimable()->create([
        'tenant_id' => $team->tenant_id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jonas',
        'last_name' => 'Maes',
    ]);

    expect(WorkerDeviceSession::resolveIdentityOnTeam($team, 'Jonas', 'Maes')['status'])->toBe('claimable');
});

it('geeft ambiguous bij meerdere naamgelijke workers', function () {
    $team = authTeam();
    Worker::factory()->count(2)->withIcon('heart')->create([
        'tenant_id' => $team->tenant_id,
        'internal_team_id' => $team->id,
        'first_name' => 'Alex',
        'last_name' => 'Janssens',
    ]);

    expect(WorkerDeviceSession::resolveIdentityOnTeam($team, 'Alex', 'Janssens')['status'])->toBe('ambiguous');
});

it('geeft not_found bij een onbekende naam', function () {
    $team = authTeam();

    expect(WorkerDeviceSession::resolveIdentityOnTeam($team, 'Niemand', 'Hier')['status'])->toBe('not_found');
});

it('bevestigt het juiste icoon en verifieert de worker', function () {
    $team = authTeam();
    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $team->tenant_id,
        'internal_team_id' => $team->id,
    ]);

    $confirmed = WorkerVerification::confirmIcon($team, 'star');

    expect($confirmed)->not->toBeNull()
        ->and($confirmed->id)->toBe($worker->id)
        ->and(WorkerVerification::verifiedWorker($team)?->id)->toBe($worker->id);
});

it('weigert een verkeerd icoon en blokkeert na 2 foute pogingen', function () {
    $team = authTeam();
    Worker::factory()->withIcon('star')->create([
        'tenant_id' => $team->tenant_id,
        'internal_team_id' => $team->id,
    ]);

    expect(WorkerVerification::confirmIcon($team, 'moon'))->toBeNull();
    WorkerIconGuard::recordFailedAttempt($team);
    expect(WorkerIconGuard::isBlocked($team))->toBeFalse()
        ->and(WorkerIconGuard::remainingAttempts($team))->toBe(1);

    WorkerIconGuard::recordFailedAttempt($team);
    expect(WorkerIconGuard::isBlocked($team))->toBeTrue()
        ->and(WorkerIconGuard::remainingAttempts($team))->toBe(0);
});

it('registreert een nieuwe worker met icoon via open registratie', function () {
    $team = authTeam();

    $result = WorkerDeviceSession::registerWorkerForTeam($team, 'Nieuwe', 'Worker', 'bell');

    expect($result['worker']->field_icon_slug)->toBe('bell')
        ->and($result['worker']->internal_team_id)->toBe($team->id)
        ->and($result['worker']->devices()->count())->toBe(1);
});

it('laat meerdere workers hetzelfde icoon kiezen', function () {
    $team = authTeam();

    Worker::factory()->withIcon('star')->create([
        'tenant_id' => $team->tenant_id,
        'internal_team_id' => $team->id,
        'first_name' => 'Eerste',
        'last_name' => 'Worker',
    ]);

    $second = WorkerDeviceSession::registerWorkerForTeam($team, 'Tweede', 'Worker', 'star');

    expect($second['worker']->field_icon_slug)->toBe('star');
});

it('bevestigt icoon voor de worker die al op naam is gekoppeld', function () {
    $team = authTeam();
    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $team->tenant_id,
        'internal_team_id' => $team->id,
    ]);
    Worker::factory()->withIcon('star')->create([
        'tenant_id' => $team->tenant_id,
        'internal_team_id' => $team->id,
    ]);

    $confirmed = WorkerVerification::confirmIconForWorker($team, $worker, 'star');

    expect($confirmed)->not->toBeNull()
        ->and($confirmed->id)->toBe($worker->id);
});
