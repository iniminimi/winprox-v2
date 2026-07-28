<?php

use App\Actions\Public\AssertPublicReportRateLimitAction;
use App\Actions\Public\RecordPublicReportRateLimitAction;
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
use App\Models\QrLinkPhoto;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitGpsReport;
use App\Models\Worker;
use App\Support\PageHelp;
use App\Support\Portal\WorkerDeviceSession;
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

it('includes portal open graph meta tags on the unit portal page', function () {
    app()->setLocale('nl');
    unitPortalScaffold();

    $og2Path = public_path('images/promo/og_2.jpg');
    if (! is_file($og2Path) && is_file(public_path('images/promo/og_1.jpg'))) {
        copy(public_path('images/promo/og_1.jpg'), $og2Path);
    }

    $this->get(route('public.unit-portal', ['token' => 'unit-token']))
        ->assertOk()
        ->assertSee('property="og:title" content="'.__('portal.social.og_title').'"', false)
        ->assertSee('property="og:description" content="'.__('portal.social.og_description').'"', false)
        ->assertSee('/images/promo/og_2.jpg', false);
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

it('shows public page help before worker sign-in on the unit portal', function () {
    app()->setLocale('nl');
    unitPortalScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(PageHelp::for('portal.unit')['title'], false)
        ->assertDontSee(PageHelp::for('portal.team')['title'], false);
});

it('shows worker page help after icon sign-in on the unit portal', function () {
    app()->setLocale('nl');
    ['team' => $team, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Sam',
        'last_name' => 'Worker',
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(PageHelp::for('portal.unit')['title'], false)
        ->set('first_name', 'Sam')
        ->set('last_name', 'Worker')
        ->call('identifyWorker')
        ->assertSee(PageHelp::for('portal.unit')['title'], false)
        ->set('sign_in_icon_slug', 'star')
        ->call('signInWithIcon')
        ->assertHasNoErrors()
        ->assertSee(PageHelp::for('portal.team')['title'], false)
        ->assertDontSee(PageHelp::for('portal.unit')['title'], false);
});

it('shows worker page help when a remembered device is verified on load', function () {
    app()->setLocale('nl');
    ['team' => $team, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    WorkerDeviceSession::bindRememberedWorker($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(PageHelp::for('portal.team')['title'], false)
        ->assertDontSee(PageHelp::for('portal.unit')['title'], false);
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
        ->assertDontSee('wire:click="openSection(\'issues\')"');

    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and($task->fresh()->completed_at)->not->toBeNull()
        ->and($issue->fresh()->status)->toBe(TaskStatus::Done)
        ->and($issue->updates()->where('kind', 'worker_note')->count())->toBe(1);
});

it('slaagt erin afhandelingsfoto’s via het portaal op te slaan gekoppeld aan de taak', function () {
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
        'status' => TaskStatus::InProgress,
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => now()->subHour(),
    ]);

    WorkerVerification::markVerified($team, $worker);

    $jpeg = base64_decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A0AAA/9k=',
        true,
    );
    $file = UploadedFile::fake()->createWithContent('portal-done.jpg', $jpeg, 'image/jpeg');

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('beginCompleteTask', $task->id)
        ->set('completingPhotos', [$file])
        ->call('submitCompleteTask')
        ->assertHasNoErrors()
        ->assertSet('portalSection', 'task_done');

    $update = $issue->fresh()->updates()->where('kind', 'worker_photos')->first();
    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and($update)->not->toBeNull()
        ->and($update?->task_id)->toBe($task->id)
        ->and($update?->photos)->toHaveCount(1)
        ->and($update?->photos->first()?->hasPublicFile())->toBeTrue();
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
        'description' => 'Kraan vervangen en afdichting controleren.',
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
        ->assertSee($task->description)
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

    expect($betweenTaskAndIssue)->toContain($task->description)
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
        'description' => null,
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
        'description' => 'Taak voor niet-goedgekeurde melding.',
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(__('portal.worker.open_tasks_with_count', ['count' => 0]))
        ->assertSee(__('portal.worker.no_open_tasks'))
        ->assertSee('hasOpenTasks: false', false)
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
        'description' => 'Controleer de leiding.',
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(__('portal.worker.open_tasks_with_count', ['count' => 3]), false)
        ->assertSee('hasOpenTasks: true', false)
        ->assertSee("'wp-portal-open-tasks-card--attention': hasOpenTasks && !open", false);
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

it('hides verification-required documents from anonymous portal visitors', function () {
    ['unit' => $unit, 'tenant' => $tenant, 'location' => $location] = unitPortalScaffold();

    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'title' => 'Open huisregels',
        'requires_verification' => false,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'title' => 'Vertrouwelijk contract',
        'requires_verification' => true,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'documents')
        ->assertSee('Open huisregels')
        ->assertDontSee('Vertrouwelijk contract')
        ->assertSee(__('portal.documents.download'));
});

it('lets a verified worker see and download verification-required documents', function () {
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
        'description' => 'Actuele mededeling',
        'original_language' => 'nl',
        'is_active' => true,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addWeek(),
    ]);
    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Verlopen mededeling',
        'original_language' => 'nl',
        'is_active' => true,
        'published_at' => now()->subWeeks(2),
        'expires_at' => now()->subWeek(),
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'announcements')
        ->assertSee('Actuele mededeling')
        ->assertDontSee('Verlopen mededeling');
});

it('shows translated announcement text when portal locale differs from source', function () {
    app()->instance(\App\Services\Translation\TranslationProviderInterface::class, new \Tests\Support\FakeTranslationProvider);

    ['unit' => $unit, 'tenant' => $tenant, 'location' => $location] = unitPortalScaffold();

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Morgen onderhoud',
        'original_language' => 'nl',
        'is_active' => true,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addWeek(),
    ]);

    app(\App\Actions\Communication\EnsureAnnouncementTranslationSlotsAction::class)->handle($announcement);
    app(\App\Actions\Communication\TranslateAnnouncementAction::class)->handle($announcement, 'en');

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('switchLocale', 'en')
        ->call('openSection', 'announcements')
        ->assertSee('[en] Morgen onderhoud');
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
        'description' => 'Nieuwe update met foto als bewijs.',
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

it('lets a verified worker store unit photos up to the remaining slots', function () {
    Storage::fake('public');
    ['unit' => $unit, 'team' => $team, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    QrLinkPhoto::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'qr_code_id' => null,
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('newPortalPhotos', [
            UploadedFile::fake()->create('unit-a.jpg', 120, 'image/jpeg'),
            UploadedFile::fake()->create('unit-b.jpg', 120, 'image/jpeg'),
        ])
        ->call('updateUnitPhotos')
        ->assertHasNoErrors()
        ->assertSet('newPortalPhotos', [])
        ->assertSee(__('portal.unit.photos_updated'));

    expect($unit->fresh()->qrLinkPhotos()->count())->toBe(4);
});

it('lets a verified worker record unit gps coordinates', function () {
    ['unit' => $unit, 'team' => $team, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('gpsLatitude', 51.05)
        ->set('gpsLongitude', 3.72)
        ->set('gpsReportedAt', '2026-06-13T14:30:00+02:00')
        ->call('updateUnitGps')
        ->assertHasNoErrors()
        ->assertSee(__('portal.unit.gps_updated'));

    $unit->refresh()->load('latestGpsReport');
    expect($unit->latestGpsReport?->latitude)->toBe(51.05)
        ->and($unit->latestGpsReport?->longitude)->toBe(3.72)
        ->and($unit->latestGpsReport?->worker_id)->toBe($worker->id);
});

it('lets a reporter record gps when the category allows it', function () {
    ['unit' => $unit] = unitPortalScaffold();
    $unit->category->update(['allow_gps_location' => true]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('gpsLatitude', 51.11)
        ->set('gpsLongitude', 3.81)
        ->set('gpsReportedAt', '2026-06-13T14:35:00+02:00')
        ->call('updateUnitGps')
        ->assertHasNoErrors();

    expect($unit->fresh()->latestGpsReport)->not->toBeNull();
});

it('rejects gps capture for reporters when the category does not allow it', function () {
    unitPortalScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('gpsLatitude', 51.11)
        ->set('gpsLongitude', 3.81)
        ->set('gpsReportedAt', '2026-06-13T14:35:00+02:00')
        ->call('updateUnitGps')
        ->assertHasErrors(['gpsLatitude']);
});

it('hides navigate to location from reporters even when gps history exists', function () {
    ['unit' => $unit, 'tenant' => $tenant] = unitPortalScaffold();
    $unit->category->update(['allow_gps_location' => true]);

    UnitGpsReport::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'latitude' => 51.05,
        'longitude' => 3.72,
        'reported_at' => now(),
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertDontSee(__('portal.worker.navigate_to_location'))
        ->assertSee(__('portal.unit.recapture_gps'));
});

it('shows navigate to location for a verified worker when gps history exists', function () {
    ['unit' => $unit, 'team' => $team, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    UnitGpsReport::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'latitude' => 51.05,
        'longitude' => 3.72,
        'reported_at' => now(),
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(__('portal.worker.navigate_to_location'));
});

it('rejects an overly long worker completion note', function () {
    ['unit' => $unit, 'team' => $team, 'location' => $location, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'status' => TaskStatus::InProgress,
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('beginCompleteTask', $task->id)
        ->set('completingNote', str_repeat('a', 501))
        ->call('submitCompleteTask')
        ->assertHasErrors(['completingNote']);

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('lets a verified worker upload a unit background photo', function () {
    Storage::fake('public');
    ['unit' => $unit, 'team' => $team, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('backgroundPhoto', [
            UploadedFile::fake()->create('background.jpg', 120, 'image/jpeg'),
        ])
        ->call('uploadBackgroundPhoto')
        ->assertHasNoErrors()
        ->assertSet('backgroundPhoto', [])
        ->assertSee(__('portal.unit.background_photo_updated'));

    $unit->refresh();
    expect($unit->background_photo_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists((string) $unit->background_photo_path))->toBeTrue();
});

it('rejects unit photo uploads that exceed the four-photo limit', function () {
    Storage::fake('public');
    ['unit' => $unit, 'team' => $team, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    QrLinkPhoto::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'qr_code_id' => null,
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('newPortalPhotos', [
            UploadedFile::fake()->create('unit-a.jpg', 120, 'image/jpeg'),
            UploadedFile::fake()->create('unit-b.jpg', 120, 'image/jpeg'),
        ])
        ->call('updateUnitPhotos')
        ->assertHasErrors(['newPortalPhotos']);

    expect($unit->fresh()->qrLinkPhotos()->count())->toBe(3);
});

it('hides the new report tile from public visitors when unit reports are disabled', function () {
    unitPortalScaffold(['public_reports_enabled' => false]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertDontSee(__('portal.tiles.new'))
        ->call('openSection', 'new')
        ->assertSet('portalSection', 'home')
        ->set('description', 'Verborgen melding')
        ->call('submitReport');

    expect(Issue::count())->toBe(0);
});

it('lets a signed-in worker create a report when public reports are disabled for visitors', function () {
    ['team' => $team, 'tenant' => $tenant] = unitPortalScaffold(['public_reports_enabled' => false]);

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(__('portal.tiles.new'))
        ->set('description', 'Worker melding op info-only unit.')
        ->call('submitReport')
        ->assertHasNoErrors()
        ->assertSet('portalSection', 'issues');

    expect(Issue::count())->toBe(1);
});

it('blokkeert anonieme burgers na het bereiken van de rate limit per unit', function () {
    cache()->flush();
    config([
        'portal.public_report_rate_limit.cooldown.decay_seconds' => 0,
        'portal.public_report_rate_limit.per_unit.max_attempts' => 2,
        'portal.public_report_rate_limit.per_unit.decay_seconds' => 900,
        'portal.public_report_rate_limit.per_tenant.max_attempts' => 50,
    ]);

    unitPortalScaffold();

    $component = Livewire::test(UnitPortal::class, ['token' => 'unit-token']);

    foreach (['Eerste melding met tekst.', 'Tweede melding met tekst.'] as $description) {
        $component
            ->call('openSection', 'new')
            ->set('description', $description)
            ->call('submitReport')
            ->assertHasNoErrors();
    }

    $component
        ->call('openSection', 'new')
        ->set('description', 'Derde melding met tekst.')
        ->call('submitReport')
        ->assertHasErrors('description');

    expect(Issue::count())->toBe(2);
});

it('past geen rate limit toe op veldworkers', function () {
    cache()->flush();
    config([
        'portal.public_report_rate_limit.per_unit.max_attempts' => 1,
        'portal.public_report_rate_limit.per_unit.decay_seconds' => 900,
    ]);

    ['team' => $team, 'tenant' => $tenant] = unitPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    $component = Livewire::test(UnitPortal::class, ['token' => 'unit-token']);

    foreach (['Worker melding een.', 'Worker melding twee.'] as $description) {
        $component
            ->call('openSection', 'new')
            ->set('description', $description)
            ->call('submitReport')
            ->assertHasNoErrors();
    }

    expect(Issue::count())->toBe(2);
});

it('telt alleen succesvolle meldingen mee voor de rate limit', function () {
    cache()->flush();
    config([
        'portal.public_report_rate_limit.per_unit.max_attempts' => 1,
        'portal.public_report_rate_limit.per_unit.decay_seconds' => 900,
    ]);

    unitPortalScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'ab')
        ->call('submitReport')
        ->assertHasErrors('description');

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'Geldige eerste melding.')
        ->call('submitReport')
        ->assertHasNoErrors();

    expect(Issue::count())->toBe(1);
});

it('blokkeert via de tenant-brede rate limit', function () {
    cache()->flush();
    config([
        'portal.public_report_rate_limit.cooldown.decay_seconds' => 0,
        'portal.public_report_rate_limit.per_unit.max_attempts' => 50,
        'portal.public_report_rate_limit.per_tenant.max_attempts' => 1,
        'portal.public_report_rate_limit.per_tenant.decay_seconds' => 3600,
    ]);

    unitPortalScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'Eerste melding met tekst.')
        ->call('submitReport')
        ->assertHasNoErrors();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'Tweede melding met tekst.')
        ->call('submitReport')
        ->assertHasErrors('description');

    expect(Issue::count())->toBe(1);
});

it('assert public report rate limit action gooit bij te veel pogingen', function () {
    cache()->flush();
    config([
        'portal.public_report_rate_limit.cooldown.decay_seconds' => 0,
        'portal.public_report_rate_limit.per_unit.max_attempts' => 1,
        'portal.public_report_rate_limit.per_unit.decay_seconds' => 60,
    ]);

    $assert = app(AssertPublicReportRateLimitAction::class);
    $record = app(RecordPublicReportRateLimitAction::class);

    $record->handle(1, 1, '127.0.0.1');

    expect(fn () => $assert->handle(1, 1, '127.0.0.1'))
        ->toThrow(\App\Exceptions\Public\PublicReportRateLimitExceededException::class);
});

it('blokkeert een tweede melding binnen de cooldown maar laat na de cooldown weer toe', function () {
    cache()->flush();
    config([
        'portal.public_report_rate_limit.cooldown.decay_seconds' => 180,
        'portal.public_report_rate_limit.per_unit.max_attempts' => 5,
        'portal.public_report_rate_limit.per_unit.decay_seconds' => 1800,
        'portal.public_report_rate_limit.per_tenant.max_attempts' => 50,
    ]);

    unitPortalScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'Eerste melding met tekst.')
        ->call('submitReport')
        ->assertHasNoErrors();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'Tweede melding binnen de cooldown.')
        ->call('submitReport')
        ->assertHasErrors('description');

    expect(Issue::count())->toBe(1);

    $this->travel(181)->seconds();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'Derde melding na de cooldown.')
        ->call('submitReport')
        ->assertHasNoErrors();

    expect(Issue::count())->toBe(2);
});

it('toont de cooldown-uitleg in de actieve locale', function () {
    cache()->flush();
    config([
        'portal.public_report_rate_limit.cooldown.decay_seconds' => 180,
        'portal.public_report_rate_limit.per_unit.max_attempts' => 5,
        'portal.public_report_rate_limit.per_tenant.max_attempts' => 50,
    ]);

    ['unit' => $unit] = unitPortalScaffold();

    $component = Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'new')
        ->set('description', 'Eerste melding met tekst.')
        ->call('submitReport')
        ->assertHasNoErrors();

    $component
        ->call('openSection', 'new')
        ->set('description', 'Tweede melding binnen de cooldown.')
        ->call('submitReport')
        ->assertHasErrors('description');

    $seconds = max(1, \Illuminate\Support\Facades\RateLimiter::availableIn(
        app(\App\Actions\Public\AssertPublicReportRateLimitAction::class)
            ->cooldownKey((int) $unit->tenant_id, (int) $unit->id, '127.0.0.1')
    ));
    $minutes = max(1, (int) ceil($seconds / 60));
    $locale = (string) $component->get('locale');

    expect($component->errors()->first('description'))->toBe(
        __('portal.report.errors.cooldown', ['seconds' => $seconds, 'minutes' => $minutes], $locale)
    );
});

it('telt cooldown-geblokkeerde meldingen niet mee voor het venster', function () {
    cache()->flush();
    config([
        'portal.public_report_rate_limit.cooldown.decay_seconds' => 180,
        'portal.public_report_rate_limit.per_unit.max_attempts' => 5,
        'portal.public_report_rate_limit.per_unit.decay_seconds' => 1800,
        'portal.public_report_rate_limit.per_tenant.max_attempts' => 50,
    ]);

    ['unit' => $unit] = unitPortalScaffold();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'Eerste melding met tekst.')
        ->call('submitReport')
        ->assertHasNoErrors();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->set('description', 'Geblokkeerd door cooldown.')
        ->call('submitReport')
        ->assertHasErrors('description');

    $unitKey = app(AssertPublicReportRateLimitAction::class)
        ->unitKey($unit->tenant_id, $unit->id, '127.0.0.1');

    expect(\Illuminate\Support\Facades\RateLimiter::attempts($unitKey))->toBe(1);
});
