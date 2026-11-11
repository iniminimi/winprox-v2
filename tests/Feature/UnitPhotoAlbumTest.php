<?php

use App\Actions\Settings\UpdateTenantPhotoAlbumAction;
use App\Livewire\Pages\Settings;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('zet het fotoalbum aan voor de hele organisatie', function () {
    $tenant = Tenant::factory()->create(['photo_album_enabled' => false]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSee('wire:model.live="photoAlbumEnabled"', false)
        ->set('photoAlbumEnabled', true);

    expect($tenant->fresh()->photoAlbumEnabled())->toBeTrue();
});

it('toont het vinkje niet in Checkmate', function () {
    $tenant = Tenant::factory()->create(['checkmate_mode' => true, 'photo_album_enabled' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertDontSee('wire:model.live="photoAlbumEnabled"', false);

    expect($tenant->fresh()->photoAlbumEnabled())->toBeFalse();
});

it('weigert het album aan te zetten in Checkmate', function () {
    $tenant = Tenant::factory()->create(['checkmate_mode' => true]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    expect(fn () => app(UpdateTenantPhotoAlbumAction::class)->handle($tenant, true, (int) $admin->id))
        ->toThrow(InvalidArgumentException::class, 'checkmate');
});
