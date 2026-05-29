<?php

use App\Enums\TaskStatus;
use App\Livewire\Public\FieldPortal;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\IssueUpdate;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('werkt een taakstatus bij via het veldportaal en herberekent de meldingstatus', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'field_qr_token' => 'veld-token',
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
    ]);

    Livewire::test(FieldPortal::class, ['token' => 'veld-token'])
        ->call('selectWorker', $worker->id)
        ->assertSet('workerId', $worker->id)
        ->call('setStatus', $task->id, 'in_progress')
        ->assertHasNoErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($issue->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('voegt een notitie toe als de uitvoerder (worker)', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'field_qr_token' => 'veld-token-2',
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
    ]);

    Livewire::test(FieldPortal::class, ['token' => 'veld-token-2'])
        ->call('selectWorker', $worker->id)
        ->set("notes.{$task->id}", 'Onderdeel besteld, kom morgen terug.')
        ->call('addNote', $task->id)
        ->assertHasNoErrors();

    $update = IssueUpdate::first();

    expect($update)->not->toBeNull()
        ->and($update->issue_id)->toBe($issue->id)
        ->and($update->worker_id)->toBe($worker->id)
        ->and($update->body)->toBe('Onderdeel besteld, kom morgen terug.');
});

it('blurt een niet-goedgekeurde melding op het publieke veldportaal', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'field_qr_token' => 'veld-token-blur',
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_at' => null,
    ]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
    ]);

    Livewire::test(FieldPortal::class, ['token' => 'veld-token-blur'])
        ->call('selectWorker', $worker->id)
        ->assertSeeHtml('wp-pending-review');
});

it('geeft 404 voor een onbekend team-token', function () {
    $this->get('/team/bestaat-niet')->assertNotFound();
});
