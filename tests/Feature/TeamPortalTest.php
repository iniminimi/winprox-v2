<?php

use App\Enums\TaskStatus;
use App\Livewire\Public\TeamPortal;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{tenant: Tenant, team: InternalTeam}
 */
function teamPortalScaffold(array $teamOverrides = []): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'field_qr_token' => 'team-token',
    ], $teamOverrides));

    return compact('tenant', 'team');
}

it('returns 404 for an unknown team token', function () {
    $this->get('/team/bestaat-niet')->assertNotFound();
});

it('allows onboarding when the chosen icon is already used by a colleague', function () {
    ['team' => $team, 'tenant' => $tenant] = teamPortalScaffold();

    Worker::factory()->withIcon('heart')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    Livewire::test(TeamPortal::class, ['token' => 'team-token'])
        ->call('showRegister')
        ->set('first_name', 'Nora')
        ->set('last_name', 'Janssen')
        ->set('selected_icon_slug', 'heart')
        ->call('completeOnboarding')
        ->assertHasNoErrors();

    expect(Worker::where('internal_team_id', $team->id)->where('field_icon_slug', 'heart')->count())->toBe(2);
});

it('shows the open-registration form when the team has no active workers', function () {
    ['team' => $team, 'tenant' => $tenant] = teamPortalScaffold();

    Livewire::test(TeamPortal::class, ['token' => 'team-token'])
        ->assertSet('inactiveReasonKey', null)
        ->set('first_name', 'Nora')
        ->set('last_name', 'Janssen')
        ->set('selected_icon_slug', 'star')
        ->call('completeOnboarding')
        ->assertHasNoErrors();

    $worker = Worker::where('internal_team_id', $team->id)->first();

    expect($worker)->not->toBeNull()
        ->and($worker->first_name)->toBe('Nora')
        ->and($worker->field_icon_slug)->toBe('star')
        ->and(WorkerDevice::where('worker_id', $worker->id)->count())->toBe(1);
});

it('is read-only: a verified worker sees tasks but no start/complete actions', function () {
    ['team' => $team, 'tenant' => $tenant] = teamPortalScaffold();

    $worker = Worker::factory()->withIcon('heart')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => now()]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
    ]);

    WorkerDeviceSession::bindRememberedWorker($team, $worker);

    Livewire::test(TeamPortal::class, ['token' => 'team-token'])
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertHasNoErrors()
        ->assertSee('Dit is een overzicht. Taken afhandelen kan alleen via de unit-QR ter plaatse.')
        ->assertDontSeeHtml('wire:click="startTask')
        ->assertDontSeeHtml('wire:click="beginCompleteTask');
});

it('blurs unapproved issue content on the team portal', function () {
    ['team' => $team, 'tenant' => $tenant] = teamPortalScaffold();

    $worker = Worker::factory()->withIcon('moon')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => null]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
    ]);

    WorkerDeviceSession::bindRememberedWorker($team, $worker);

    Livewire::test(TeamPortal::class, ['token' => 'team-token'])
        ->set('sign_in_icon_slug', 'moon')
        ->call('signInWithIcon')
        ->assertHasNoErrors()
        ->assertSeeHtml('wp-pending-review');
});

it('signs in an existing worker via name then icon confirmation', function () {
    ['team' => $team, 'tenant' => $tenant] = teamPortalScaffold();

    $worker = Worker::factory()->withIcon('key')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Sam',
        'last_name' => 'de Vries',
    ]);

    WorkerDeviceSession::bindRememberedWorker($team, $worker);

    Livewire::test(TeamPortal::class, ['token' => 'team-token'])
        ->set('sign_in_icon_slug', 'key')
        ->call('signInWithIcon')
        ->assertHasNoErrors()
        ->assertSee('Open taken');

    expect(WorkerVerification::verifiedWorker($team)?->id)->toBe($worker->id);
});

it('treats a name without an icon as claimable and opens registration', function () {
    ['team' => $team, 'tenant' => $tenant] = teamPortalScaffold();

    Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    Worker::factory()->claimable()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Pim',
        'last_name' => 'Bakker',
    ]);

    Livewire::test(TeamPortal::class, ['token' => 'team-token'])
        ->set('first_name', 'Pim')
        ->set('last_name', 'Bakker')
        ->call('identifyWorker')
        ->assertHasNoErrors()
        ->assertSet('showRegisterForm', true);
});

it('shows an inactive notice when the team is inactive', function () {
    teamPortalScaffold(['is_active' => false]);

    Livewire::test(TeamPortal::class, ['token' => 'team-token'])
        ->assertSet('inactiveReasonKey', 'portal.inactive.team_inactive')
        ->assertSee('Niet beschikbaar');
});
