<?php

use App\Enums\TaskStatus;
use App\Livewire\Public\UnitPortal;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{tenant: Tenant, location: Location, team: InternalTeam, unit: Unit}
 */
function unitPortalScaffold(array $unitOverrides = []): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $unit = Unit::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'default_internal_team_id' => $team->id,
        'is_active' => true,
        'qr_token' => 'unit-token',
    ], $unitOverrides));

    return compact('tenant', 'location', 'team', 'unit');
}

it('creates an unapproved issue + auto task + photos via a valid unit token', function () {
    Storage::fake('public');
    ['unit' => $unit, 'team' => $team, 'location' => $location, 'tenant' => $tenant] = unitPortalScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'De kraan lekt al dagen in de keuken.')
        ->set('photos', [
            UploadedFile::fake()->create('foto1.jpg', 120, 'image/jpeg'),
            UploadedFile::fake()->create('foto2.jpg', 120, 'image/jpeg'),
        ])
        ->call('submitReport')
        ->assertHasNoErrors()
        ->assertSet('portalSection', 'issues');

    $issue = Issue::first();

    expect($issue)->not->toBeNull()
        ->and($issue->tenant_id)->toBe($tenant->id)
        ->and($issue->unit_id)->toBe($unit->id)
        ->and($issue->location_id)->toBe($location->id)
        ->and($issue->isApproved())->toBeFalse()
        ->and($issue->photos()->count())->toBe(2);

    $task = Task::where('issue_id', $issue->id)->first();
    expect($task)->not->toBeNull()
        ->and($task->internal_team_id)->toBe($team->id)
        ->and($task->status)->toBe(TaskStatus::New);

    foreach ($issue->photos as $photo) {
        Storage::disk('public')->assertExists($photo->path);
    }
});

it('requires a description of at least 3 characters', function () {
    unitPortalScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'ab')
        ->call('submitReport')
        ->assertHasErrors('description');

    expect(Issue::count())->toBe(0);
});

it('returns 404 for an unknown unit token', function () {
    $this->get('/melden/bestaat-niet')->assertNotFound();
});

it('blurs unapproved issue content on the public portal', function () {
    ['unit' => $unit, 'tenant' => $tenant, 'location' => $location] = unitPortalScaffold();

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'description' => 'Gevoelige onbevestigde melding.',
        'status' => TaskStatus::New,
        'approved_at' => null,
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'issues')
        ->assertSee('Wacht op controle');
});

it('lets a verified field worker start and complete a task (issue rolls up to done)', function () {
    Storage::fake('public');
    ['unit' => $unit, 'team' => $team, 'location' => $location, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

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
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('startTask', $task->id)
        ->assertHasNoErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($task->fresh()->started_at)->not->toBeNull();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('beginCompleteTask', $task->id)
        ->set('completingNote', 'Lekkage verholpen.')
        ->call('submitCompleteTask')
        ->assertHasNoErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and($task->fresh()->completed_at)->not->toBeNull()
        ->and($issue->fresh()->status)->toBe(TaskStatus::Done)
        ->and($issue->updates()->where('kind', 'worker_note')->count())->toBe(1);
});

it('hides worker UI from anonymous citizen visitors', function () {
    ['team' => $team, 'tenant' => $tenant] = unitPortalScaffold();
    Worker::factory()->withIcon('heart')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSet('portalSection', 'home')
        ->assertDontSee('Aanmelden als medewerker');
});

it('only allows downloading public documents that do not require verification', function () {
    ['unit' => $unit, 'tenant' => $tenant, 'location' => $location] = unitPortalScaffold();

    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'title' => 'Open huisregels',
        'is_public' => true,
        'requires_verification' => false,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'title' => 'Vertrouwelijk contract',
        'is_public' => true,
        'requires_verification' => true,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'documents')
        ->assertSee('Open huisregels')
        ->assertSee('Vertrouwelijk contract')
        ->assertSee('Verificatie vereist')
        ->assertSee('Downloaden');
});

it('shows active announcements but hides expired ones', function () {
    ['unit' => $unit, 'tenant' => $tenant, 'location' => $location] = unitPortalScaffold();

    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'title' => 'Actuele mededeling',
        'is_active' => true,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addWeek(),
    ]);
    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'title' => 'Verlopen mededeling',
        'is_active' => true,
        'published_at' => now()->subWeeks(2),
        'expires_at' => now()->subWeek(),
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'announcements')
        ->assertSee('Actuele mededeling')
        ->assertDontSee('Verlopen mededeling');
});

it('shows an inactive notice when the unit is inactive', function () {
    unitPortalScaffold(['is_active' => false]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSet('inactiveReasonKey', 'portal.inactive.unit_inactive')
        ->assertSee('Niet beschikbaar');
});
