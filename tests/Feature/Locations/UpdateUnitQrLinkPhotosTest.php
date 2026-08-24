<?php

use App\Actions\Locations\UpdateUnitAction;
use App\Livewire\Locations\Show;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\QrLinkPhoto;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('stores qr-link photos on unit update even without an active qr code', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Hoogtewerker 1',
    ]);

    app(UpdateUnitAction::class)->handle($unit, [
        'name' => 'Hoogtewerker 1',
        'description' => null,
    ], $user->id, [
        UploadedFile::fake()->image('qr-sticker.jpg', 200, 200),
    ]);

    $photo = $unit->fresh()->qrLinkPhotos()->first();

    expect($unit->fresh()->qrLinkPhotos()->count())->toBe(1)
        ->and($photo)->not->toBeNull()
        ->and($photo->qr_code_id)->toBeNull()
        ->and(Storage::disk('public')->exists($photo->path))->toBeTrue();
});

it('attaches qr-link photos to the active qr code when the unit has one', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lift A',
    ]);
    $qrCode = QrCode::factory()->forUnit($unit)->create([
        'tenant_id' => $tenant->id,
    ]);

    app(UpdateUnitAction::class)->handle($unit, [
        'name' => 'Lift A',
        'description' => null,
    ], $user->id, [
        UploadedFile::fake()->image('sticker-closeup.jpg', 200, 200),
    ]);

    $photo = $unit->fresh()->qrLinkPhotos()->first();

    expect($unit->fresh()->qrLinkPhotos()->count())->toBe(1)
        ->and($photo?->qr_code_id)->toBe($qrCode->id)
        ->and(Storage::disk('public')->exists($photo->path))->toBeTrue();
});

it('keeps qr-link photos after saving unit edit on the location page', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Printer A',
    ]);

    Livewire::actingAs($user)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->set('unitPhotos', [
            UploadedFile::fake()->image('qr-link.jpg', 200, 200),
        ])
        ->call('saveUnit')
        ->assertHasNoErrors();

    expect(QrLinkPhoto::query()->where('unit_id', $unit->id)->count())->toBe(1)
        ->and($unit->fresh()->qrLinkPhotos()->first()?->hasPublicFile())->toBeTrue();
});
