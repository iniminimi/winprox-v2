<?php

use App\Actions\PhotoAlbum\DeleteUnitAlbumPhotoAction;
use App\Actions\PhotoAlbum\ListWorkerUnitAlbumAction;
use App\Actions\PhotoAlbum\SendUnitAlbumPhotosAction;
use App\Actions\PhotoAlbum\UpdateUnitAlbumRecipientAction;
use App\Actions\PhotoAlbum\UploadUnitAlbumPhotoAction;
use App\Actions\Retention\PruneUnitAlbumPhotosAction;
use App\Actions\Settings\UpdateTenantPhotoAlbumAction;
use App\Data\PhotoAlbum\SendUnitAlbumPhotosData;
use App\Enums\EmailUnsubscribeSource;
use App\Livewire\Pages\PhotoAlbumIndex;
use App\Livewire\Pages\Settings;
use App\Livewire\Public\TimePortal;
use App\Mail\UnitAlbumPhotosMail;
use App\Models\ClockPoint;
use App\Models\EmailUnsubscribe;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function albumTenant(): array
{
    $tenant = Tenant::factory()->create(['photo_album_enabled' => true, 'has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'is_active' => true,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);

    return [$tenant, $admin, $unit, $worker];
}

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

it('bewaart een verkleinde albumfoto tot het dagmaximum', function () {
    Storage::fake('local');
    config(['unit_photo_album.max_photos_per_day' => 1]);
    [$tenant, , $unit, $worker] = albumTenant();
    $upload = app(UploadUnitAlbumPhotoAction::class);
    $file = UploadedFile::fake()->image('baby.jpg');

    $photo = $upload->handle($unit, $file, (int) $tenant->id, $worker);

    expect($photo->taken_on->toDateString())->toBe(now()->toDateString())
        ->and(Storage::disk('local')->exists($photo->path))->toBeTrue();

    expect(fn () => $upload->handle($unit, UploadedFile::fake()->image('twee.jpg'), (int) $tenant->id, $worker))
        ->toThrow(InvalidArgumentException::class, 'album_day_full');
});

it('weigert een foto wanneer het album uit staat', function () {
    Storage::fake('local');
    [$tenant, , $unit, $worker] = albumTenant();
    $tenant->update(['photo_album_enabled' => false]);

    expect(fn () => app(UploadUnitAlbumPhotoAction::class)->handle(
        $unit->fresh(),
        UploadedFile::fake()->image('baby.jpg'),
        (int) $tenant->id,
        $worker,
    ))->toThrow(InvalidArgumentException::class, 'album_disabled');
});

it('mailt de fotos meteen en weigert een afgemeld adres', function () {
    Storage::fake('local');
    Mail::fake();
    [$tenant, $admin, $unit, $worker] = albumTenant();
    $photo = app(UploadUnitAlbumPhotoAction::class)->handle(
        $unit,
        UploadedFile::fake()->image('baby.jpg'),
        (int) $tenant->id,
        $worker,
    );

    $sends = app(SendUnitAlbumPhotosAction::class)->handle(
        new SendUnitAlbumPhotosData((int) $unit->id, [(int) $photo->id], 'ouder@example.com', 'Ouder'),
        (int) $tenant->id,
        (int) $admin->id,
    );

    expect($sends)->toHaveCount(1);
    Mail::assertSent(UnitAlbumPhotosMail::class, fn (UnitAlbumPhotosMail $mail): bool => $mail->hasTo('ouder@example.com'));

    $recipient = $unit->fresh();
    expect(\App\Models\UnitAlbumRecipient::query()->where('email', 'ouder@example.com')->value('description'))->toBe('Ouder');

    app(UpdateUnitAlbumRecipientAction::class)->handle(
        \App\Models\UnitAlbumRecipient::query()->where('email', 'ouder@example.com')->first(),
        'Mama',
        (int) $tenant->id,
        (int) $admin->id,
    );
    expect(\App\Models\UnitAlbumRecipient::query()->where('email', 'ouder@example.com')->value('description'))->toBe('Mama');

    EmailUnsubscribe::query()->create([
        'email' => 'stop@example.com',
        'source' => EmailUnsubscribeSource::Manual,
        'unsubscribed_at' => now(),
    ]);

    expect(fn () => app(SendUnitAlbumPhotosAction::class)->handle(
        new SendUnitAlbumPhotosData((int) $unit->id, [(int) $photo->id], 'stop@example.com'),
        (int) $tenant->id,
        (int) $admin->id,
    ))->toThrow(InvalidArgumentException::class, 'unsubscribed');

    unset($recipient);
});

it('laat een uitvoerder alleen de eigen foto van vandaag wissen', function () {
    Storage::fake('local');
    [$tenant, , $unit, $worker] = albumTenant();
    $other = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $worker->internal_team_id,
        'is_active' => true,
    ]);
    $photo = app(UploadUnitAlbumPhotoAction::class)->handle(
        $unit,
        UploadedFile::fake()->image('baby.jpg'),
        (int) $tenant->id,
        $worker,
    );
    $delete = app(DeleteUnitAlbumPhotoAction::class);

    expect(fn () => $delete->handle($photo, (int) $tenant->id, null, $other))
        ->toThrow(InvalidArgumentException::class, 'album_photo_not_own');

    $delete->handle($photo->fresh(), (int) $tenant->id, null, $worker);

    expect(UnitAlbumPhoto::query()->find($photo->id))->toBeNull();
});

it('wist albumfotos ouder dan de bewaartermijn', function () {
    Storage::fake('local');
    [$tenant, , $unit, $worker] = albumTenant();
    $photo = app(UploadUnitAlbumPhotoAction::class)->handle(
        $unit,
        UploadedFile::fake()->image('oud.jpg'),
        (int) $tenant->id,
        $worker,
    );
    $photo->forceFill(['created_at' => now()->subDays(8)])->save();

    $stats = app(PruneUnitAlbumPhotosAction::class)->handle(false, now());

    expect($stats['removed'])->toBe(1)
        ->and(UnitAlbumPhoto::query()->find($photo->id))->toBeNull();
});

it('toont de fototegel op Clock Point alleen als het album aan staat', function () {
    [$tenant, , , $worker] = albumTenant();
    $worker->update(['field_icon_slug' => 'heart']);
    ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'album-clock',
    ]);

    Livewire::test(TimePortal::class, ['token' => 'album-clock'])
        ->set('first_name', $worker->first_name)
        ->set('last_name', $worker->last_name)
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', $worker->field_icon_slug)
        ->call('signInWithIcon')
        ->assertSeeHtml('wire:click="openAlbum"');

    $tenant->update(['photo_album_enabled' => false]);

    Livewire::test(TimePortal::class, ['token' => 'album-clock'])
        ->set('first_name', $worker->first_name)
        ->set('last_name', $worker->last_name)
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', $worker->field_icon_slug)
        ->call('signInWithIcon')
        ->assertDontSeeHtml('wire:click="openAlbum"');
});

it('toont het fotoalbum in beheer wanneer het aan staat', function () {
    [$tenant, $admin, $unit] = albumTenant();

    Livewire::actingAs($admin)
        ->test(PhotoAlbumIndex::class)
        ->assertSee(__('photo_album.title'), false)
        ->set('unitId', $unit->id)
        ->assertSee($unit->name, false);

    $listed = app(ListWorkerUnitAlbumAction::class)->handle(
        Worker::query()->where('tenant_id', $tenant->id)->first(),
        (int) $tenant->id,
        (int) $unit->id,
    );

    expect($listed['units']->pluck('id')->all())->toContain($unit->id);
});
