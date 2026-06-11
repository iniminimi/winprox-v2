<?php

use App\Enums\TaskStatus;
use App\Livewire\Public\UnitPortal;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Document;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\IssuePhoto;
use App\Models\IssueUpdate;
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
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Test Category']);
    $category->teams()->sync([$team->id]);

    $qrToken = $unitOverrides['qr_token'] ?? 'unit-token';
    unset($unitOverrides['qr_token']);

    $unit = Unit::factory()->withQrToken($qrToken)->create(array_merge([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
    ], $unitOverrides));

    return compact('tenant', 'location', 'team', 'unit');
}

it('shows tenant welcome title and unit context above home tiles', function () {
    ['unit' => $unit, 'location' => $location, 'tenant' => $tenant] = unitPortalScaffold(['name' => 'Printer A']);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(__('portal.welcome_title', ['tenant' => $tenant->name]), false)
        ->assertSee($location->name, false)
        ->assertSee('Printer A', false);
});

it('creates an unapproved issue + auto task + photos via a valid unit token', function () {
    Storage::fake('public');
    ['unit' => $unit, 'team' => $team, 'location' => $location, 'tenant' => $tenant] = unitPortalScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('reporter_first_name', 'Jan')
        ->set('reporter_last_name', 'Melder')
        ->set('reporter_email', 'jan.melder@example.test')
        ->set('description', 'De kraan lekt al dagen in de keuken.')
        ->set('photos', [
            UploadedFile::fake()->create('foto1.jpg', 120, 'image/jpeg'),
            UploadedFile::fake()->create('foto2.jpg', 120, 'image/jpeg'),
        ])
        ->call('submitReport')
        ->assertHasNoErrors()
        ->assertSet('portalSection', 'issues')
        ->assertSee(__('portal.pending_review'))
        ->assertDontSee('De kraan lekt al dagen in de keuken.');

    $issue = Issue::first();

    expect($issue)->not->toBeNull()
        ->and($issue->tenant_id)->toBe($tenant->id)
        ->and($issue->unit_id)->toBe($unit->id)
        ->and($issue->location_id)->toBe($location->id)
        ->and($issue->reporter_name)->toBe('Jan Melder')
        ->and($issue->reporter_contact)->toBe('jan.melder@example.test')
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
        ->set('reporter_first_name', 'Jan')
        ->set('reporter_last_name', 'Melder')
        ->set('reporter_email', 'jan@example.test')
        ->set('description', 'ab')
        ->call('submitReport')
        ->assertHasErrors('description');

    expect(Issue::count())->toBe(0);
});

it('allows anonymous submit without reporter fields', function () {
    ['unit' => $unit, 'tenant' => $tenant] = unitPortalScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'Lekkage in de keuken.')
        ->call('submitReport')
        ->assertHasNoErrors();

    $issue = Issue::first();
    expect($issue)->not->toBeNull()
        ->and($issue->unit_id)->toBe($unit->id)
        ->and($issue->reporter_name)->toBeNull()
        ->and($issue->reporter_contact)->toBeNull();
});

it('returns 404 for an unknown unit token', function () {
    $this->get('/melden/bestaat-niet')->assertNotFound();
});

it('blurs unapproved issue content on the public portal', function () {
    ['unit' => $unit, 'tenant' => $tenant, 'location' => $location] = unitPortalScaffold();

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'description' => 'Gevoelige onbevestigde melding.',
        'status' => TaskStatus::New,
        'approved_at' => null,
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'issues')
        ->assertSee(__('portal.pending_review'))
        ->assertDontSee('Gevoelige onbevestigde melding.')
        ->call('openIssueDetail', $issue->id)
        ->assertSet('portalSection', 'issues')
        ->assertSet('selectedIssueId', null);
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
        ->assertHasNoErrors()
        ->assertSet('portalSection', 'task_done')
        ->assertSee(__('portal.worker.task_completed'))
        ->assertDontSee(__('portal.tiles.issues'));

    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and($task->fresh()->completed_at)->not->toBeNull()
        ->and($issue->fresh()->status)->toBe(TaskStatus::Done)
        ->and($issue->updates()->where('kind', 'worker_note')->count())->toBe(1);
});

it('shows task nr and issue line on the worker portal and hides photos missing on disk', function () {
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
        'description' => 'Lekkende kraan in de keuken.',
        'status' => TaskStatus::New,
        'approved_at' => now(),
        'created_at' => now()->setTime(14, 30),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'note' => 'Kraan vervangen en afdichting controleren.',
    ]);

    $onDisk = IssuePhoto::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'path' => 'issue-photos/on-disk.jpg',
    ]);
    Storage::disk('public')->put($onDisk->path, 'jpeg');

    IssuePhoto::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'path' => 'issue-photos/missing.jpg',
    ]);

    WorkerVerification::markVerified($team, $worker);

    $component = Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(__('portal.worker.task_heading', ['nr' => $task->id]))
        ->assertSee($task->note)
        ->assertSee(__('portal.worker.issue_heading', ['nr' => $issue->id]))
        ->assertSee(__('portal.worker.issue_meta', [
            'description' => $issue->description,
            'datetime' => $issue->created_at->isoFormat('D MMM YYYY, HH:mm'),
        ]))
        ->assertSee('/storage/issue-photos/on-disk.jpg', false)
        ->assertDontSee('/storage/issue-photos/missing.jpg', false);

    $html = $component->html();
    $taskHeading = __('portal.worker.task_heading', ['nr' => $task->id]);
    $issueHeading = __('portal.worker.issue_heading', ['nr' => $issue->id]);
    $taskBlockStart = strpos($html, $taskHeading);
    $issueHeadingPos = strpos($html, $issueHeading, $taskBlockStart);
    $betweenTaskAndIssue = substr($html, $taskBlockStart + strlen($taskHeading), $issueHeadingPos - $taskBlockStart - strlen($taskHeading));

    expect($betweenTaskAndIssue)->toContain($task->note)
        ->and($betweenTaskAndIssue)->not->toContain($issue->description);
});

it('does not fall back to issue description when task note is empty', function () {
    ['unit' => $unit, 'team' => $team, 'location' => $location, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'description' => 'Lekkende kraan in de keuken.',
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'note' => null,
    ]);

    WorkerVerification::markVerified($team, $worker);

    $component = Livewire::test(UnitPortal::class, ['token' => 'unit-token']);
    $html = $component->html();
    $taskHeading = __('portal.worker.task_heading', ['nr' => $task->id]);
    $issueHeading = __('portal.worker.issue_heading', ['nr' => $issue->id]);
    $taskBlockStart = strpos($html, $taskHeading);
    $issueHeadingPos = strpos($html, $issueHeading, $taskBlockStart);
    $betweenTaskAndIssue = substr($html, $taskBlockStart + strlen($taskHeading), $issueHeadingPos - $taskBlockStart - strlen($taskHeading));

    expect($betweenTaskAndIssue)->not->toContain($issue->description);
});

it('hides open tasks for unapproved issues from the worker portal', function () {
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
        'description' => 'Lek in de gang.',
        'status' => TaskStatus::New,
        'approved_at' => null,
    ]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'note' => 'Taak voor niet-goedgekeurde melding.',
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(__('portal.worker.open_tasks_with_count', ['count' => 0]))
        ->assertSee(__('portal.worker.no_open_tasks'))
        ->assertDontSee('wp-portal-open-tasks-card--attention', false)
        ->assertDontSee('Lek in de gang.')
        ->assertDontSee('Taak voor niet-goedgekeurde melding.');
});

it('pulses the open tasks card when the worker has open tasks', function () {
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

    Task::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'note' => 'Controleer de leiding.',
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(__('portal.worker.open_tasks_with_count', ['count' => 3]), false)
        ->assertSee('wp-portal-open-tasks-card--attention', false);
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

it('shows worker photo hints in the active portal locale', function () {
    ['team' => $team, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    $genericEnHint = trans('portal.report.photos.hint', [], 'en');

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('switchLocale', 'en')
        ->assertSee('Take a photo of the surroundings. This photo will be used as the background image for this unit (refresh).', false)
        ->assertSee('Take a close-up photo of the QR code and a photo from further away.', false)
        ->assertSee('Add environment photo', false)
        ->assertDontSee($genericEnHint, false)
        ->call('switchLocale', 'fr')
        ->assertSee('rafraîchissement', false)
        ->assertSee('code QR et une photo de plus loin', false)
        ->assertSee('Ajouter une photo', false)
        ->assertDontSee('Take a photo of the surroundings.', false)
        ->assertDontSee('Add environment photo', false);
});

it('scopes documents to unit, category and location-wide entries', function () {
    ['unit' => $unit, 'tenant' => $tenant, 'location' => $location] = unitPortalScaffold();
    $category = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kranen',
    ]);
    $otherCategory = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hotelkamers',
    ]);
    $unit->update(['category_id' => $category->id]);

    $otherUnit = \App\Models\Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $otherCategory->id,
        'name' => 'Andere machine',
    ]);

    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'title' => 'Handleiding deze unit',
        'is_public' => true,
        'requires_verification' => false,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $otherUnit->id,
        'title' => 'Handleiding andere unit',
        'is_public' => true,
        'requires_verification' => false,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => null,
        'title' => 'Algemeen gebouwreglement',
        'is_public' => true,
        'requires_verification' => false,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => null,
        'category_id' => $category->id,
        'title' => 'Handleiding categorie kranen',
        'is_public' => true,
        'requires_verification' => false,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => null,
        'category_id' => $otherCategory->id,
        'title' => 'Handleiding categorie hotel',
        'is_public' => true,
        'requires_verification' => false,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'documents')
        ->assertSee('Handleiding deze unit')
        ->assertSee('Handleiding categorie kranen')
        ->assertSee('Algemeen gebouwreglement')
        ->assertDontSee('Handleiding andere unit')
        ->assertDontSee('Handleiding categorie hotel');
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
        ->assertSee(__('portal.documents.verification_required'))
        ->assertSee(__('portal.documents.download'));
});

it('lets a verified worker download verification-required documents', function () {
    ['unit' => $unit, 'team' => $team, 'tenant' => $tenant, 'location' => $location] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('key')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'title' => 'Intern contract',
        'is_public' => true,
        'requires_verification' => true,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'documents')
        ->assertSee('Intern contract')
        ->assertSee(__('portal.documents.download'));
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
        ->assertSee(__('portal.inactive.title'));
});

it('shows issue updates with photos on issue detail', function () {
    Storage::fake('public');
    ['unit' => $unit, 'tenant' => $tenant, 'location' => $location] = unitPortalScaffold();

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'description' => 'Lek in gang A.',
        'status' => TaskStatus::InProgress,
        'approved_at' => now(),
    ]);

    $update = IssueUpdate::query()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'kind' => 'worker_note',
        'body' => 'Nieuwe update met foto als bewijs.',
    ]);

    IssuePhoto::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'issue_update_id' => $update->id,
        'path' => 'issue-photos/update-on-disk.jpg',
    ]);
    Storage::disk('public')->put('issue-photos/update-on-disk.jpg', 'jpeg');

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openIssueDetail', $issue->id)
        ->assertSee(__('portal.issue.updates_title'))
        ->assertSee('Nieuwe update met foto als bewijs.')
        ->assertSee('/storage/issue-photos/update-on-disk.jpg', false);
});
