<?php

declare(strict_types=1);

use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function tlPhotoEncryptionKey(): void
{
    if (filled((string) config('app.key'))) {
        return;
    }

    $raw = str_repeat('w', 32);
    config(['app.key' => 'base64:'.base64_encode($raw)]);
    app()->instance('encrypter', new Encrypter($raw, (string) config('app.cipher')));
}

/** @return array{Tenant, InternalTeam, ClockPoint, Worker} */
function tlPhotoContext(): array
{
    tlPhotoEncryptionKey();
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'qr_token' => 'cp-tl-photo-'.$tenant->id,
    ]);
    $teamleader = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
        'is_teamleader' => true,
        'first_name' => 'Team',
        'last_name' => 'Leader',
        'field_icon_slug' => 'crown',
    ]);

    return [$tenant, $team, $clockPoint, $teamleader];
}

function tlPhotoSignIn(ClockPoint $clockPoint, Worker $worker)
{
    return Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', $worker->first_name)
        ->set('last_name', $worker->last_name)
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', $worker->field_icon_slug)
        ->call('signInWithIcon');
}

function tlPhotoColleague(Tenant $tenant, InternalTeam $team): Worker
{
    return Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
        'first_name' => 'Piet',
        'last_name' => 'Colleague',
        'field_icon_slug' => 'heart',
    ]);
}

it('laat een teamleader een foto van een collega opslaan via het Clock Point-portaal', function () {
    Storage::fake('public');
    [$tenant, $team, $clockPoint, $teamleader] = tlPhotoContext();
    $colleague = tlPhotoColleague($tenant, $team);

    tlPhotoSignIn($clockPoint, $teamleader)
        ->call('startWorkerPhoto', $colleague->id)
        ->assertSet('photoTargetWorkerId', $colleague->id)
        ->set('teamleaderPhoto', UploadedFile::fake()->image('portret.jpg'))
        ->assertHasNoErrors()
        ->assertSet('photoTargetWorkerId', null);

    $colleague->refresh();
    expect($colleague->photo_path)->toStartWith('worker-photos/');
    Storage::disk('public')->assertExists($colleague->photo_path);
});

it('laat een teamleader ook zijn eigen foto bijwerken', function () {
    Storage::fake('public');
    [, , $clockPoint, $teamleader] = tlPhotoContext();

    tlPhotoSignIn($clockPoint, $teamleader)
        ->call('startWorkerPhoto', $teamleader->id)
        ->set('teamleaderPhoto', UploadedFile::fake()->image('selfie.png'))
        ->assertHasNoErrors();

    $teamleader->refresh();
    expect($teamleader->photo_path)->toStartWith('worker-photos/');
});

it('laat een teamleader een bestaande foto verwijderen', function () {
    Storage::fake('public');
    [$tenant, $team, $clockPoint, $teamleader] = tlPhotoContext();
    $colleague = tlPhotoColleague($tenant, $team);
    Storage::disk('public')->put('worker-photos/oud.jpg', 'fake-image');
    $colleague->update(['photo_path' => 'worker-photos/oud.jpg']);

    tlPhotoSignIn($clockPoint, $teamleader)
        ->call('removeWorkerPhoto', $colleague->id)
        ->assertHasNoErrors();

    expect($colleague->fresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing('worker-photos/oud.jpg');
});

it('weigert de foto-actie voor een gewone uitvoerder', function () {
    Storage::fake('public');
    [$tenant, $team, $clockPoint] = tlPhotoContext();
    $ordinary = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
        'is_teamleader' => false,
        'first_name' => 'Gewoon',
        'last_name' => 'Worker',
        'field_icon_slug' => 'star',
    ]);
    $colleague = tlPhotoColleague($tenant, $team);

    tlPhotoSignIn($clockPoint, $ordinary)
        ->call('startWorkerPhoto', $colleague->id)
        ->assertSet('photoTargetWorkerId', null);

    tlPhotoSignIn($clockPoint, $ordinary)
        ->set('photoTargetWorkerId', $colleague->id)
        ->set('teamleaderPhoto', UploadedFile::fake()->image('hack.jpg'));

    expect($colleague->fresh()->photo_path)->toBeNull();
});

it('weigert een foto voor een worker van een ander team', function () {
    Storage::fake('public');
    [$tenant, , $clockPoint, $teamleader] = tlPhotoContext();
    $otherTeam = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $outsider = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $otherTeam->id,
        'is_active' => true,
        'first_name' => 'Anders',
        'last_name' => 'Team',
        'field_icon_slug' => 'gem',
    ]);

    tlPhotoSignIn($clockPoint, $teamleader)
        ->call('startWorkerPhoto', $outsider->id)
        ->assertSet('photoTargetWorkerId', null);

    tlPhotoSignIn($clockPoint, $teamleader)
        ->set('photoTargetWorkerId', $outsider->id)
        ->set('teamleaderPhoto', UploadedFile::fake()->image('hack.jpg'));

    expect($outsider->fresh()->photo_path)->toBeNull();
});

it('vervangt een bestaande foto en ruimt het oude bestand op', function () {
    Storage::fake('public');
    [$tenant, $team, $clockPoint, $teamleader] = tlPhotoContext();
    $colleague = tlPhotoColleague($tenant, $team);
    Storage::disk('public')->put('worker-photos/vorige.jpg', 'fake-image');
    $colleague->update(['photo_path' => 'worker-photos/vorige.jpg']);

    tlPhotoSignIn($clockPoint, $teamleader)
        ->call('startWorkerPhoto', $colleague->id)
        ->set('teamleaderPhoto', UploadedFile::fake()->image('nieuw.jpg'))
        ->assertHasNoErrors();

    $colleague->refresh();
    expect($colleague->photo_path)->toStartWith('worker-photos/')
        ->and($colleague->photo_path)->not->toBe('worker-photos/vorige.jpg');
    Storage::disk('public')->assertMissing('worker-photos/vorige.jpg');
});
