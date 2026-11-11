<?php

use App\Actions\PhotoAlbum\DeliverUnitAlbumSendAction;
use App\Actions\PhotoAlbum\SendUnitAlbumPhotosAction;
use App\Actions\PhotoAlbum\UploadUnitAlbumPhotoAction;
use App\Actions\Retention\PruneUnitAlbumPhotosAction;
use App\Data\PhotoAlbum\SendUnitAlbumPhotosData;
use App\Enums\UnitAlbumSendStatus;
use App\Jobs\SendUnitAlbumMailJob;
use App\Livewire\Pages\PhotoAlbumIndex;
use App\Mail\UnitAlbumPhotosMail;
use App\Models\EmailUnsubscribe;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use App\Models\UnitAlbumRecipient;
use App\Models\UnitAlbumSend;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function albumContext(array $tenantAttrs = []): array
{
    Storage::fake('local');
    $tenant = Tenant::factory()->create($tenantAttrs);
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'photo_album_enabled' => true, 'name' => 'Lokaal 1']);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $unit, $worker, $admin];
}

function albumPhoto(Unit $unit, Worker $worker): UnitAlbumPhoto
{
    return app(UploadUnitAlbumPhotoAction::class)->handle($unit, UploadedFile::fake()->image('a.jpg', 100, 100), (int) $unit->tenant_id, $worker);
}

it('bewaart foto privé en stopt bij het maximum per dag', function () {
    [, $unit, $worker] = albumContext();
    config(['unit_photo_album.max_photos_per_day' => 2]);

    $photo = albumPhoto($unit, $worker);
    albumPhoto($unit, $worker);

    Storage::disk('local')->assertExists($photo->path);
    expect($photo->path)->toStartWith('unit-album/'.$unit->tenant_id.'/'.$unit->id.'/');
    expect(fn () => albumPhoto($unit, $worker))->toThrow(InvalidArgumentException::class, 'album_day_full');
});

it('weigert upload als het album uit staat of in Checkmate', function () {
    [, $unit, $worker] = albumContext();
    $unit->update(['photo_album_enabled' => false]);
    expect(fn () => albumPhoto($unit->fresh(), $worker))->toThrow(InvalidArgumentException::class, 'album_disabled');

    [, $cmUnit, $cmWorker] = albumContext(['checkmate_mode' => true]);
    expect(fn () => albumPhoto($cmUnit, $cmWorker))->toThrow(InvalidArgumentException::class, 'album_disabled');
});

it('stuurt naar één ontvanger in mails van maximaal 8 foto\'s en bewaart het adres', function () {
    Queue::fake();
    [$tenant, $unit, $worker, $admin] = albumContext();
    $ids = collect(range(1, 10))->map(fn () => albumPhoto($unit, $worker)->id)->all();

    $sends = app(SendUnitAlbumPhotosAction::class)->handle(
        new SendUnitAlbumPhotosData($unit->id, $ids, 'Ouder@Example.com', 'mama van Lotte'),
        $tenant->id,
        $admin->id,
    );

    expect($sends)->toHaveCount(2)
        ->and($sends->pluck('photo_count')->all())->toBe([8, 2])
        ->and($sends->pluck('recipient_email')->unique()->all())->toBe(['ouder@example.com']);
    expect(UnitAlbumRecipient::query()->where('unit_id', $unit->id)->first())
        ->email->toBe('ouder@example.com')
        ->description->toBe('mama van Lotte');
    Queue::assertPushed(SendUnitAlbumMailJob::class, 2);
});

it('weigert meteen een afgemeld adres met een melding', function () {
    Queue::fake();
    [$tenant, $unit, $worker, $admin] = albumContext();
    EmailUnsubscribe::query()->create(['email' => 'weg@example.com', 'source' => 'voluntary', 'unsubscribed_at' => now()]);
    $photo = albumPhoto($unit, $worker);

    try {
        app(SendUnitAlbumPhotosAction::class)->handle(new SendUnitAlbumPhotosData($unit->id, [$photo->id], 'weg@example.com'), $tenant->id, $admin->id);
        $this->fail('geen fout');
    } catch (ValidationException $e) {
        expect($e->errors()['email'][0])->toContain('weg@example.com');
    }
    expect(UnitAlbumSend::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('plant mails boven de uurlimiet per tenant later in', function () {
    Queue::fake();
    config(['unit_photo_album.mails_per_hour_per_tenant' => 2, 'unit_photo_album.max_photos_per_mail' => 1]);
    [$tenant, $unit, $worker, $admin] = albumContext();
    $ids = collect(range(1, 3))->map(fn () => albumPhoto($unit, $worker)->id)->all();

    $sends = app(SendUnitAlbumPhotosAction::class)->handle(new SendUnitAlbumPhotosData($unit->id, $ids, 'ouder@example.com'), $tenant->id, $admin->id);

    expect($sends[0]->scheduled_at->isFuture())->toBeFalse()
        ->and($sends[2]->scheduled_at->greaterThan(now()->addMinutes(55)))->toBeTrue();
});

it('verstuurt de mail met ingesloten foto\'s en markeert afgemeld als niet verstuurd', function () {
    Mail::fake();
    [$tenant, $unit, $worker, $admin] = albumContext();
    $photo = albumPhoto($unit, $worker);
    $send = UnitAlbumSend::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'user_id' => $admin->id, 'recipient_email' => 'ouder@example.com', 'photo_ids' => [$photo->id], 'photo_count' => 1]);

    app(DeliverUnitAlbumSendAction::class)->handle($send->id);
    expect($send->fresh()->status)->toBe(UnitAlbumSendStatus::Sent);
    Mail::assertSent(UnitAlbumPhotosMail::class, fn ($mail) => $mail->hasTo('ouder@example.com'));

    EmailUnsubscribe::query()->create(['email' => 'later@example.com', 'source' => 'voluntary', 'unsubscribed_at' => now()]);
    $blocked = UnitAlbumSend::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'recipient_email' => 'later@example.com', 'photo_ids' => [$photo->id], 'photo_count' => 1]);
    app(DeliverUnitAlbumSendAction::class)->handle($blocked->id);
    expect($blocked->fresh()->status)->toBe(UnitAlbumSendStatus::BlockedUnsubscribed);
});

it('rendert de mail met cid-afbeeldingen zonder links naar foto\'s', function () {
    [$tenant, $unit, $worker] = albumContext();
    $photo = albumPhoto($unit, $worker);
    $send = UnitAlbumSend::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'photo_ids' => [$photo->id], 'photo_count' => 1]);

    $send->load('unit.tenant');
    expect((new UnitAlbumPhotosMail($send, collect([$photo])))->render())
        ->toContain('data:image/')->toContain('Lokaal 1')->not->toContain('unit-album/');

    config(['mail.default' => 'array']);
    Mail::to('ouder@example.com')->send(new UnitAlbumPhotosMail($send, collect([$photo])));
    $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

    expect($sent->getHtmlBody())->toContain('cid:')->not->toContain('unit-album/')
        ->and(collect($sent->getAttachments())->filter(fn ($part) => $part->getDisposition() === 'inline'))->toHaveCount(1)
        ->and($sent->getHeaders()->has('List-Unsubscribe'))->toBeTrue();
});

it('wist foto\'s na de bewaartermijn', function () {
    [, $unit, $worker] = albumContext();
    $old = albumPhoto($unit, $worker);
    $old->forceFill(['created_at' => now()->subDays(8)])->save();
    $fresh = albumPhoto($unit, $worker);

    app(PruneUnitAlbumPhotosAction::class)->handle();

    expect(UnitAlbumPhoto::query()->pluck('id')->all())->toBe([$fresh->id]);
    Storage::disk('local')->assertMissing($old->path);
});

it('backoffice toont het album en schermt andere tenants af', function () {
    Queue::fake();
    [$tenant, $unit, $worker, $admin] = albumContext();
    $photo = albumPhoto($unit, $worker);

    Livewire::actingAs($admin)->test(PhotoAlbumIndex::class)
        ->assertSee('Lokaal 1')
        ->set('selectedPhotoIds', [$photo->id])
        ->set('recipientEmail', 'ouder@example.com')
        ->call('send')
        ->assertHasNoErrors();
    expect(UnitAlbumSend::query()->count())->toBe(1);

    $this->actingAs($admin)->get(route('photo-album.photos.show', $photo))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    [, , , $otherAdmin] = albumContext();
    $this->actingAs($otherAdmin)->get(route('photo-album.photos.show', $photo))->assertNotFound();
});
