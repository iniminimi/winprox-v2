<?php

declare(strict_types=1);

use App\Actions\Locations\DeleteUnitAction;
use App\Actions\Retention\PruneCameraPhotosAction;
use App\Livewire\Cameras\Show;
use App\Models\Camera;
use App\Models\CameraClaim;
use App\Models\CameraPhoto;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('koppelt een camera en bewaart een kopie alleen als doorsturen aan staat', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    seedTenantPastOnboarding($tenant);
    Tenancy::actAs($tenant->id);

    $location = Location::query()->where('tenant_id', $tenant->id)->first();
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Inkomhal',
    ]);

    $this->actingAs($admin)
        ->get(route('locations.show', $location))
        ->assertOk()
        ->assertSee(route('cameras.show', $unit), false);

    $page = Livewire::actingAs($admin)
        ->test(Show::class, ['unit' => $unit])
        ->assertSee('Inkomhal')
        ->assertDontSee('wpcam_')
        ->call('issueCode');

    $code = $page->get('pairingCode');
    expect($code)->toHaveLength(8);

    $claim = $this->postJson(route('api.v1.cameras.claim'), [
        'code' => substr($code, 0, 4).'-'.substr($code, 4),
        'device_hint' => 'XIAO-inkom',
    ])->assertCreated()->json('data.claim_token');

    $page->call('confirmClaim', CameraClaim::query()->first()->id)
        ->assertDontSee('wpcam_');

    $status = $this->getJson(route('api.v1.cameras.claim-status', $claim))->assertOk();
    $key = $status->json('data.camera_key');
    expect($key)->toStartWith('wpcam_')
        ->and($status->json('data.forwarding'))->toBeFalse();

    $camera = Camera::query()->where('unit_id', $unit->id)->first();
    expect($camera)->not->toBeNull()
        ->and($camera->isLinked())->toBeTrue()
        ->and($camera->matchesToken($key))->toBeTrue();

    $photo = UploadedFile::fake()->image('hal.jpg');

    $this->withHeader('X-WinProx-Camera-Key', $key)
        ->post(route('api.v1.cameras.photos.store'), ['photo' => $photo])
        ->assertUnprocessable();

    expect(CameraPhoto::query()->count())->toBe(0);

    $page->call('toggleForwarding');

    $this->withHeader('X-WinProx-Camera-Key', $key)
        ->getJson(route('api.v1.cameras.status'))
        ->assertOk()
        ->assertJsonPath('data.forwarding', true);

    $stored = $this->withHeader('X-WinProx-Camera-Key', $key)
        ->post(route('api.v1.cameras.photos.store'), [
            'photo' => UploadedFile::fake()->image('hal.jpg'),
        ])
        ->assertCreated()
        ->json('data.id');

    $row = CameraPhoto::query()->findOrFail($stored);
    Storage::disk('local')->assertExists($row->path);

    $response = $this->actingAs($admin)
        ->get(route('cameras.photos.show', $row))
        ->assertOk();

    expect($response->headers->get('cache-control'))
        ->toContain('private')
        ->toContain('no-store');

    $page->call('unlink');
    $camera->refresh();
    expect($camera->forwarding_enabled)->toBeFalse()
        ->and($camera->token_hash)->toBeNull()
        ->and(CameraPhoto::query()->whereKey($row->id)->exists())->toBeTrue();

    $this->withHeader('X-WinProx-Camera-Key', $key)
        ->getJson(route('api.v1.cameras.status'))
        ->assertUnauthorized();
});

it('verbergt de camera voor een medewerker, checkmate en een andere organisatie', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);

    $this->actingAs($employee)->get(route('cameras.show', $unit))->assertForbidden();

    Tenancy::actAs($tenant->id);
    $camera = Camera::query()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'forwarding_enabled' => true,
        'paired_at' => now(),
        'token_hash' => 'abc',
        'token_prefix' => 'wpcam_test',
    ]);
    $path = 'cameras/'.$tenant->id.'/'.$camera->id.'/foto.jpg';
    Storage::disk('local')->put($path, 'foto');
    $photo = CameraPhoto::query()->create([
        'tenant_id' => $tenant->id,
        'camera_id' => $camera->id,
        'path' => $path,
        'mime' => 'image/jpeg',
    ]);

    $this->actingAs($employee)
        ->get(route('cameras.photos.show', $photo))
        ->assertForbidden();

    Tenancy::forget();

    $other = Tenant::factory()->create();
    $otherAdmin = User::factory()->admin()->create(['tenant_id' => $other->id]);
    $this->actingAs($otherAdmin)
        ->get(route('cameras.photos.show', $photo))
        ->assertNotFound();

    $checkmate = Tenant::factory()->create(['checkmate_mode' => true]);
    $checkAdmin = User::factory()->admin()->create(['tenant_id' => $checkmate->id]);
    $checkLocation = Location::factory()->create(['tenant_id' => $checkmate->id]);
    $checkUnit = Unit::factory()->create([
        'tenant_id' => $checkmate->id,
        'location_id' => $checkLocation->id,
    ]);

    $this->actingAs($checkAdmin)->get(route('cameras.show', $checkUnit))->assertNotFound();
    $this->actingAs($admin)->get(route('cameras.show', $unit))->assertOk()->assertDontSee('wpcam_');
});

it('wist een camerafoto na de bewaartermijn en bij het verwijderen van de unit', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);
    $camera = Camera::query()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'forwarding_enabled' => false,
    ]);

    $oldPath = 'cameras/'.$tenant->id.'/'.$camera->id.'/oud.jpg';
    $newPath = 'cameras/'.$tenant->id.'/'.$camera->id.'/nieuw.jpg';
    Storage::disk('local')->put($oldPath, 'oud');
    Storage::disk('local')->put($newPath, 'nieuw');

    $old = CameraPhoto::query()->create([
        'tenant_id' => $tenant->id,
        'camera_id' => $camera->id,
        'path' => $oldPath,
        'mime' => 'image/jpeg',
    ]);
    $old->forceFill([
        'created_at' => now()->subDays(31),
        'updated_at' => now()->subDays(31),
    ])->save();
    CameraPhoto::query()->create([
        'tenant_id' => $tenant->id,
        'camera_id' => $camera->id,
        'path' => $newPath,
        'mime' => 'image/jpeg',
    ]);

    app(PruneCameraPhotosAction::class)->handle();

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($newPath);
    expect(CameraPhoto::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(CameraPhoto::query()->count())->toBe(1);

    app(DeleteUnitAction::class)->handle($unit);

    Storage::disk('local')->assertMissing($newPath);
    expect(Camera::query()->whereKey($camera->id)->exists())->toBeFalse()
        ->and(CameraPhoto::query()->count())->toBe(0);
});
