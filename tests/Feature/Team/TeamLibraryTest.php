<?php

use App\Actions\Team\MarkTeamLibraryOpenedAction;
use App\Actions\Team\UpdateTeamAnnouncementAction;
use App\Actions\Team\UpdateWorkerAction;
use App\Enums\TeamLibraryKind;
use App\Livewire\Pages\Team;
use App\Livewire\Public\TimePortal;
use App\Livewire\Public\UnitPortal;
use App\Livewire\Teams\Announcements;
use App\Livewire\Teams\Documents;
use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\TeamAnnouncement;
use App\Models\TeamDocument;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('zet documenten en mededelingen op de teampagina en niet op de teamlijst', function () {
    Storage::fake('local');
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ploeg Noord']);
    $owner = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Nora',
        'last_name' => 'Noord',
    ]);

    Livewire::actingAs($admin)
        ->test(Documents::class, ['team' => $team])
        ->set('description', 'Persoonlijk contract van Nora')
        ->set('audience', 'worker')
        ->set('workerId', (string) $owner->id)
        ->set('documentFile', UploadedFile::fake()->create('contract.pdf', 40, 'application/pdf'))
        ->call('createDocument')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)
        ->test(Announcements::class, ['team' => $team])
        ->set('description', 'Huisreglement voor de ploeg')
        ->set('audience', 'team')
        ->call('createAnnouncement')
        ->assertHasNoErrors();

    $document = TeamDocument::query()->first();
    expect($document)->not->toBeNull()
        ->and($document->worker_id)->toBe($owner->id)
        ->and(Storage::disk('local')->exists($document->file_path))->toBeTrue();

    expect(TeamAnnouncement::query()->whereNull('worker_id')->where('description', 'Huisreglement voor de ploeg')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('team.index', ['section' => 'teams']))
        ->assertOk()
        ->assertDontSee('Persoonlijk contract van Nora', false);

    $this->actingAs($admin)
        ->get(route('team.show', $team))
        ->assertOk()
        ->assertSee(__('team.library.documents_title'), false)
        ->assertSee(__('team.library.announcements_title'), false)
        ->assertDontSee('Persoonlijk contract van Nora', false)
        ->assertSee(trans_choice('team.library.worker_documents_count', 1, ['count' => 1]), false)
        ->assertSee('Nora Noord', false)
        ->assertSee('Huisreglement voor de ploeg', false);

    $this->actingAs($admin)
        ->get(route('team-documents.file', $document))
        ->assertOk();
});

it('geeft een bestand alleen aan de uitvoerder voor wie het bedoeld is', function () {
    Storage::fake('local');
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $owner = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $colleague = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_teamleader' => true,
    ]);
    Storage::disk('local')->put('team-documents/'.$tenant->id.'/contract.pdf', 'geheim-contract');
    $personal = TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'worker_id' => $owner->id,
        'description' => 'Contract alleen voor de eigenaar',
        'file_path' => 'team-documents/'.$tenant->id.'/contract.pdf',
    ]);
    $shared = TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'worker_id' => null,
        'description' => 'Gedeeld huisreglement',
        'file_path' => 'team-documents/'.$tenant->id.'/contract.pdf',
    ]);

    $this->get(route('team-documents.file', $personal))->assertForbidden();

    WorkerDeviceSession::bindRememberedWorker($team, $colleague);
    $this->get(route('team-documents.file', $personal))->assertForbidden();
    $this->get(route('team-documents.file', $shared))->assertOk();

    WorkerDeviceSession::bindRememberedWorker($team, $owner);
    $this->get(route('team-documents.file', $personal))->assertOk();
});

it('verhuist een persoonlijk bestand mee naar het nieuwe team', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $from = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $to = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $from->id,
        'first_name' => 'Lisa',
        'last_name' => 'Foto',
    ]);
    $personal = TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $from->id,
        'worker_id' => $worker->id,
    ]);
    $shared = TeamAnnouncement::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $from->id,
        'worker_id' => null,
        'description' => 'Blijft op het oude team',
    ]);

    app(UpdateWorkerAction::class)->handle($worker, [
        'first_name' => 'Lisa',
        'last_name' => 'Foto',
        'internal_team_id' => $to->id,
    ], $admin->id);

    expect($personal->fresh()->internal_team_id)->toBe($to->id)
        ->and($shared->fresh()->internal_team_id)->toBe($from->id);
});

it('toont de tegels op Clock Point ook zonder prikklok en verbergt andermans contract', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $owner = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Nora',
        'last_name' => 'Noord',
        'field_icon_slug' => 'heart',
    ]);
    $colleague = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Zora',
        'last_name' => 'Zuid',
        'field_icon_slug' => 'star',
        'is_teamleader' => true,
    ]);
    $document = TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'worker_id' => $owner->id,
        'title' => 'Contract Nora',
        'description' => 'Contract alleen voor Nora',
    ]);
    $announcement = TeamAnnouncement::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'worker_id' => null,
        'description' => 'Ploegmededeling voor iedereen',
    ]);
    ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'library-clock',
    ]);

    Livewire::test(TimePortal::class, ['token' => 'library-clock'])
        ->set('first_name', 'Nora')
        ->set('last_name', 'Noord')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertDontSeeHtml('wire:click="openHours"')
        ->assertSee(__('portal.tiles.documents'), false)
        ->assertSee(__('portal.tiles.announcements'), false)
        ->call('openTeamDocuments')
        ->assertSeeHtml('class="wp-card wp-card-pad wp-stack-tight"')
        ->assertSee('Contract Nora', false)
        ->assertSee('Contract alleen voor Nora', false)
        ->assertSee(__('portal.documents.published', ['date' => $document->published_at->isoFormat('D MMM YYYY')]), false)
        ->call('closeTeamDocuments')
        ->call('openTeamAnnouncements')
        ->assertSeeHtml('class="wp-card wp-card-pad wp-stack-tight"')
        ->assertSee('Ploegmededeling voor iedereen', false)
        ->assertSee($announcement->published_at->isoFormat('D MMM YYYY'), false);

    Livewire::test(TimePortal::class, ['token' => 'library-clock'])
        ->set('first_name', 'Zora')
        ->set('last_name', 'Zuid')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'star')
        ->call('signInWithIcon')
        ->assertDontSee('Contract alleen voor Nora', false)
        ->assertSee(__('portal.tiles.announcements'), false)
        ->call('openTeamAnnouncements')
        ->assertSee('Ploegmededeling voor iedereen', false);
});

it('zet teambestanden niet op het unit-portaal', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $category->teams()->sync([$team->id]);
    Unit::factory()->withQrToken('library-unit-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
    ]);
    TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'description' => 'Contract dat niet op de unit-QR hoort',
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'library-unit-token'])
        ->assertDontSee('Contract dat niet op de unit-QR hoort', false);
});

it('stuurt een oude worker-link door naar de teampagina', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $this->actingAs($admin)
        ->get(route('team.index', ['worker' => $worker->id]))
        ->assertRedirect(route('team.show', [
            'internalTeam' => $team->id,
            'worker' => $worker->id,
        ]));
});

function teamLibrarySetup(): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Peeters',
    ]);

    return [$tenant, $admin, $team, $worker];
}

it('toont standaard alleen actieve teambrede documenten en filtert op doelgroep, status en zoekterm', function () {
    [$tenant, $admin, $team, $worker] = teamLibrarySetup();
    $base = ['tenant_id' => $tenant->id, 'internal_team_id' => $team->id];
    TeamDocument::factory()->create($base + ['description' => 'Huisreglement ploeg']);
    TeamDocument::factory()->create($base + ['description' => 'Oud reglement', 'is_active' => false]);
    TeamDocument::factory()->create($base + ['description' => 'Contract arbeider', 'worker_id' => $worker->id]);

    $component = Livewire::actingAs($admin)
        ->test(Documents::class, ['team' => $team])
        ->assertSee('Huisreglement ploeg')
        ->assertDontSee('Oud reglement')
        ->assertDontSee('Contract arbeider');

    $component->set('audienceFilter', (string) $worker->id)
        ->assertSee('Contract arbeider')
        ->assertDontSee('Huisreglement ploeg');

    $component->set('audienceFilter', 'all')
        ->assertSee('Contract arbeider')
        ->assertSee('Huisreglement ploeg')
        ->assertDontSee('Oud reglement');

    $component->set('statusFilter', 'inactive')
        ->assertSee('Oud reglement')
        ->assertDontSee('Huisreglement ploeg');

    $component->set('statusFilter', 'all')
        ->set('search', 'Peeters')
        ->assertSee('Contract arbeider')
        ->assertDontSee('Huisreglement ploeg')
        ->assertDontSee('Oud reglement');

    $component->set('search', 'zzz-niets')
        ->assertSee(__('team.library.filters.empty'));
});

it('toont verlopen mededelingen alleen onder de status verlopen', function () {
    [$tenant, $admin, $team] = teamLibrarySetup();
    $base = ['tenant_id' => $tenant->id, 'internal_team_id' => $team->id];
    TeamAnnouncement::factory()->create($base + ['description' => 'Lopende mededeling', 'expires_at' => now()->addWeek()]);
    TeamAnnouncement::factory()->create($base + ['description' => 'Verlopen mededeling', 'expires_at' => now()->subDay()]);
    TeamAnnouncement::factory()->create($base + ['description' => 'Uitgezette mededeling', 'is_active' => false]);

    Livewire::actingAs($admin)
        ->test(Announcements::class, ['team' => $team])
        ->assertSee('Lopende mededeling')
        ->assertDontSee('Verlopen mededeling')
        ->assertDontSee('Uitgezette mededeling')
        ->set('statusFilter', 'expired')
        ->assertSee('Verlopen mededeling')
        ->assertSee(__('team.library.filters.status_expired'))
        ->assertDontSee('Lopende mededeling')
        ->assertDontSee('Uitgezette mededeling')
        ->set('statusFilter', 'inactive')
        ->assertSee('Uitgezette mededeling')
        ->assertDontSee('Verlopen mededeling');
});

it('pagineert documenten en mededelingen per 25', function () {
    [$tenant, $admin, $team] = teamLibrarySetup();
    $base = ['tenant_id' => $tenant->id, 'internal_team_id' => $team->id];
    TeamDocument::factory()->count(30)->create($base);
    TeamAnnouncement::factory()->count(30)->create($base);

    Livewire::actingAs($admin)
        ->test(Documents::class, ['team' => $team])
        ->assertViewHas('documents', fn ($documents) => $documents->count() === 25 && $documents->total() === 30);

    Livewire::actingAs($admin)
        ->test(Announcements::class, ['team' => $team])
        ->assertViewHas('announcements', fn ($announcements) => $announcements->count() === 25 && $announcements->total() === 30);
});

it('toont op uitvoerders dezelfde tellers en opent de lijst van dat team', function () {
    [$tenant, $admin, $team, $worker] = teamLibrarySetup();
    TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'description' => 'Contract Jan',
        'worker_id' => $worker->id,
    ]);
    TeamAnnouncement::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'description' => 'Bericht voor Jan',
        'worker_id' => $worker->id,
    ]);

    $documentsUrl = route('team.show', [
        'internalTeam' => $team->id,
        'worker' => $worker->id,
        'library' => 'documents',
    ]);

    $this->actingAs($admin)
        ->get(route('workers.index'))
        ->assertOk()
        ->assertSee(__('team.library.manage_documents'), false)
        ->assertSee(__('team.library.manage_announcements'), false)
        ->assertSee(e($documentsUrl).'#team-documents-'.$team->id, false)
        ->assertSee(trans_choice('team.library.worker_documents_count', 1, ['count' => 1]), false);

    $this->actingAs($admin)
        ->get($documentsUrl)
        ->assertOk()
        ->assertSee('Contract Jan', false)
        ->assertDontSee('Bericht voor Jan', false);

    $this->actingAs($admin)
        ->get(route('team.show', [
            'internalTeam' => $team->id,
            'worker' => $worker->id,
            'library' => 'announcements',
        ]))
        ->assertOk()
        ->assertSee('Bericht voor Jan', false)
        ->assertDontSee('Contract Jan', false);
});

it('stuurt de teller in de uitvoerdersrij naar de documenten en mededelingen van dat team', function () {
    [$tenant, $admin, $team, $worker] = teamLibrarySetup();
    TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'worker_id' => $worker->id,
    ]);
    TeamAnnouncement::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'worker_id' => $worker->id,
    ]);

    $this->actingAs($admin)
        ->get(route('team.show', $team))
        ->assertOk()
        ->assertSee(__('team.library.manage_documents'), false)
        ->assertSee(__('team.library.manage_announcements'), false)
        ->assertSee('openWorkerDocuments('.$worker->id.')', false)
        ->assertSee('openWorkerAnnouncements('.$worker->id.')', false);

    Livewire::actingAs($admin)
        ->test(Team::class, ['internalTeam' => $team])
        ->call('openWorkerDocuments', $worker->id)
        ->assertDispatched('team-documents-show-worker', workerId: $worker->id);

    Livewire::actingAs($admin)
        ->test(Team::class, ['internalTeam' => $team])
        ->call('openWorkerAnnouncements', $worker->id)
        ->assertDispatched('team-announcements-show-worker', workerId: $worker->id);

    $other = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $outsider = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $other->id,
    ]);

    Livewire::actingAs($admin)
        ->test(Team::class, ['internalTeam' => $team])
        ->call('openWorkerDocuments', $outsider->id)
        ->assertNotDispatched('team-documents-show-worker');
});

it('opent de lijst gefilterd op de uitvoerder via de teller in de uitvoerdersrij', function () {
    [$tenant, $admin, $team, $worker] = teamLibrarySetup();
    $base = ['tenant_id' => $tenant->id, 'internal_team_id' => $team->id];
    TeamDocument::factory()->create($base + ['description' => 'Contract Jan', 'worker_id' => $worker->id]);
    TeamAnnouncement::factory()->create($base + ['description' => 'Bericht voor Jan', 'worker_id' => $worker->id]);

    Livewire::actingAs($admin)
        ->test(Documents::class, ['team' => $team])
        ->set('statusFilter', 'inactive')
        ->dispatch('team-documents-show-worker', workerId: $worker->id)
        ->assertSet('audienceFilter', (string) $worker->id)
        ->assertSet('statusFilter', 'active')
        ->assertSet('panelOpen', true)
        ->assertSee('Contract Jan');

    Livewire::actingAs($admin)
        ->test(Announcements::class, ['team' => $team])
        ->dispatch('team-announcements-show-worker', workerId: $worker->id)
        ->assertSee('Bericht voor Jan');
});

function libraryOpensSetup(): array
{
    Storage::fake('local');
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $nora = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id, 'first_name' => 'Nora', 'last_name' => 'Noord']);
    $zora = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id, 'first_name' => 'Zora', 'last_name' => 'Zuid']);
    Storage::disk('local')->put('team-documents/'.$tenant->id.'/reglement.pdf', 'reglement');
    $document = TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'description' => 'Huisreglement',
        'file_path' => 'team-documents/'.$tenant->id.'/reglement.pdf',
    ]);

    return [$tenant, $admin, $team, $nora, $zora, $document];
}

it('zet een uitvoerder bij downloaden op de lijst geopend zonder tijdstip', function () {
    [$tenant, $admin, $team, $nora, , $document] = libraryOpensSetup();
    $updatedAt = $document->updated_at->toDateTimeString();
    $this->travel(5)->minutes();

    $this->actingAs($admin)->get(route('team-documents.file', $document))->assertOk();
    expect($document->fresh()->openedWorkerIds())->toBe([]);

    auth()->logout();
    WorkerDeviceSession::bindRememberedWorker($team, $nora);
    $this->get(route('team-documents.file', $document))->assertOk();
    $this->get(route('team-documents.file', $document))->assertOk();

    $fresh = $document->fresh();
    expect($fresh->openedWorkerIds())->toBe([$nora->id])
        ->and($fresh->updated_at->toDateTimeString())->toBe($updatedAt)
        ->and(\App\Models\AuditLog::query()->where('model_type', TeamDocument::class)->where('action', 'like', '%open%')->exists())->toBeFalse();
});

it('toont bij het team hoeveel uitvoerders een item openden en wie', function () {
    [$tenant, $admin, $team, $nora, $zora, $document] = libraryOpensSetup();
    app(MarkTeamLibraryOpenedAction::class)->handle($nora, $tenant->id, TeamLibraryKind::Documents, [$document->id]);

    Livewire::actingAs($admin)
        ->test(Documents::class, ['team' => $team])
        ->assertSee(__('team.library.opened_count', ['opened' => 1, 'total' => 2]))
        ->call('showOpens', $document->id)
        ->assertSee(__('team.library.opens_title'))
        ->assertSeeInOrder(['Zora Zuid', __('team.library.not_opened'), 'Nora Noord', __('team.library.opened')])
        ->call('closeOpens')
        ->assertDontSee(__('team.library.opens_title'));
});

it('telt alle mededelingen als geopend bij het openen van het scherm en houdt ze dat bezoek als nieuw', function () {
    [$tenant, , $team, $nora] = libraryOpensSetup();
    $old = TeamAnnouncement::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id, 'description' => 'Oude mededeling']);
    app(MarkTeamLibraryOpenedAction::class)->handle($nora, $tenant->id, TeamLibraryKind::Announcements, [$old->id]);
    $new = TeamAnnouncement::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id, 'description' => 'Nieuwe mededeling']);

    $opened = app(MarkTeamLibraryOpenedAction::class)->handle($nora, $tenant->id, TeamLibraryKind::Announcements);

    expect($opened)->toBe([$new->id])
        ->and($new->fresh()->isOpenedBy($nora->id))->toBeTrue()
        ->and($old->fresh()->openedWorkerIds())->toBe([$nora->id]);
});

it('laat een gewijzigde mededeling of een nieuw bestand opnieuw openen', function () {
    [$tenant, $admin, $team, $nora, , $document] = libraryOpensSetup();
    $announcement = TeamAnnouncement::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id, 'description' => 'Eerste tekst']);
    app(MarkTeamLibraryOpenedAction::class)->handle($nora, $tenant->id, TeamLibraryKind::Announcements);
    app(MarkTeamLibraryOpenedAction::class)->handle($nora, $tenant->id, TeamLibraryKind::Documents);

    $update = app(UpdateTeamAnnouncementAction::class);
    $update->handle($announcement->fresh(), ['description' => 'Eerste tekst', 'worker_id' => null, 'is_active' => true, 'expires_at' => '2030-01-01'], $tenant->id, $admin->id);
    expect($announcement->fresh()->isOpenedBy($nora->id))->toBeTrue()
        ->and($announcement->fresh()->is_revised)->toBeFalse();

    $update->handle($announcement->fresh(), ['description' => 'Aangepaste tekst', 'worker_id' => null, 'is_active' => true, 'expires_at' => null], $tenant->id, $admin->id);
    expect($announcement->fresh()->openedWorkerIds())->toBe([])
        ->and($announcement->fresh()->is_revised)->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Documents::class, ['team' => $team])
        ->call('openEditModal', $document->id)
        ->set('documentFile', UploadedFile::fake()->create('reglement-v2.pdf', 20, 'application/pdf'))
        ->call('updateDocument')
        ->assertHasNoErrors();

    expect($document->fresh()->openedWorkerIds())->toBe([])
        ->and($document->fresh()->is_revised)->toBeTrue();
});

it('zet op Clock Point ongeopende items bovenaan met Nieuw of Gewijzigd', function () {
    [$tenant, , $team, $nora, , $document] = libraryOpensSetup();
    $nora->update(['field_icon_slug' => 'heart']);
    $opened = TeamDocument::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id, 'title' => 'Al gelezen', 'description' => 'Al gelezen']);
    $document->update(['title' => 'Nieuw reglement', 'is_revised' => true]);
    app(MarkTeamLibraryOpenedAction::class)->handle($nora, $tenant->id, TeamLibraryKind::Documents, [$opened->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id, 'qr_token' => 'library-opens']);

    Livewire::test(TimePortal::class, ['token' => 'library-opens'])
        ->set('first_name', 'Nora')
        ->set('last_name', 'Noord')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('openTeamDocuments')
        ->assertSeeInOrder([__('portal.library.revised'), 'Nieuw reglement', 'Al gelezen'])
        ->assertSeeHtml('wp-portal-library-item--opened');
});

