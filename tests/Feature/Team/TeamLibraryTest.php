<?php

use App\Actions\Team\UpdateWorkerAction;
use App\Livewire\Public\TimePortal;
use App\Livewire\Public\UnitPortal;
use App\Livewire\Teams\Announcements;
use App\Livewire\Teams\Documents;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\TeamAnnouncement;
use App\Models\TeamDocument;
use App\Models\Tenant;
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
        ->assertSee('Persoonlijk contract van Nora', false)
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
    TeamDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'worker_id' => $owner->id,
        'description' => 'Contract alleen voor Nora',
    ]);
    TeamAnnouncement::factory()->create([
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
        ->assertSee(__('portal.worker_library.documents_title'), false)
        ->assertSee(__('portal.worker_library.announcements_title'), false)
        ->call('openTeamDocuments')
        ->assertSee('Contract alleen voor Nora', false)
        ->call('closeTeamDocuments')
        ->call('openTeamAnnouncements')
        ->assertSee('Ploegmededeling voor iedereen', false);

    Livewire::test(TimePortal::class, ['token' => 'library-clock'])
        ->set('first_name', 'Zora')
        ->set('last_name', 'Zuid')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'star')
        ->call('signInWithIcon')
        ->assertDontSee('Contract alleen voor Nora', false)
        ->assertSee(__('portal.worker_library.announcements_title'), false)
        ->call('openTeamAnnouncements')
        ->assertSee('Ploegmededeling voor iedereen', false);
});

it('zet teambestanden niet op het unit-portaal', function () {
    ['tenant' => $tenant, 'team' => $team] = unitPortalScaffold(['qr_token' => 'library-unit-token']);
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
