<?php

use App\Actions\Team\DeleteWorkerPhotoAction;
use App\Actions\Team\UpdateWorkerPhotoAction;
use App\Livewire\Pages\Team;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * @return array{0: Tenant, 1: User}
 */
function workerPhotoTenantWithAdmin(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Foto NV']);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $admin];
}

it('slaat een worker-foto op via de action', function () {
    [$tenant, $admin] = workerPhotoTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $file = UploadedFile::fake()->image('portrait.jpg', 800, 800);

    $updated = app(UpdateWorkerPhotoAction::class)->handle($worker, $file, (int) $admin->id);

    expect($updated->photo_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($updated->photo_path))->toBeTrue()
        ->and($updated->photoPublicUrl())->not->toBeNull();
});

it('vervangt en verwijdert een worker-foto', function () {
    [$tenant, $admin] = workerPhotoTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $first = app(UpdateWorkerPhotoAction::class)->handle(
        $worker,
        UploadedFile::fake()->image('one.jpg', 400, 400),
        (int) $admin->id,
    );
    $firstPath = $first->photo_path;

    $second = app(UpdateWorkerPhotoAction::class)->handle(
        $first,
        UploadedFile::fake()->image('two.jpg', 400, 400),
        (int) $admin->id,
    );

    expect($second->photo_path)->not->toBe($firstPath)
        ->and(Storage::disk('public')->exists($firstPath))->toBeFalse()
        ->and(Storage::disk('public')->exists($second->photo_path))->toBeTrue();

    $secondPath = $second->photo_path;
    $cleared = app(DeleteWorkerPhotoAction::class)->handle($second, (int) $admin->id);

    expect($cleared->photo_path)->toBeNull()
        ->and(Storage::disk('public')->exists($secondPath))->toBeFalse();
});

it('toont de worker-foto in teams- en uitvoerderslijst', function () {
    [$tenant, $admin] = workerPhotoTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $path = UploadedFile::fake()->image('avatar.jpg', 200, 200)->store('worker-photos', 'public');
    Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Lisa',
        'last_name' => 'Foto',
        'photo_path' => $path,
    ]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('toggleTeam', $team->id)
        ->assertSee('Lisa Foto')
        ->assertSee(Storage::disk('public')->url($path), false);

    $this->actingAs($admin)
        ->get(route('workers.index'))
        ->assertOk()
        ->assertSee('Lisa Foto')
        ->assertSee(Storage::disk('public')->url($path), false);
});

it('slaat foto op via uitvoerder-modal', function () {
    [$tenant, $admin] = workerPhotoTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openAddWorker', $team->id)
        ->set('workerFirstName', 'Nora')
        ->set('workerLastName', 'Portret')
        ->set('workerPhoto', UploadedFile::fake()->image('nora.jpg', 320, 320))
        ->call('saveWorker')
        ->assertHasNoErrors();

    $worker = Worker::query()->where('first_name', 'Nora')->first();
    expect($worker)->not->toBeNull()
        ->and($worker->photo_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($worker->photo_path))->toBeTrue();
});
