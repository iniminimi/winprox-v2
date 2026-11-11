<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\UnitAlbumPhoto;
use App\Models\UnitAlbumSend;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Foto's uit het album van een unit, ingesloten in de mail (geen links,
 * geen remote images — WINPROX_RULES.md §14). Vast onderwerp; uitschrijffooter
 * + List-Unsubscribe via AppendEmailUnsubscribeFooterToMessage. Bewust geen
 * X-WinProx-Transactional: een afgemeld adres krijgt deze mail niet.
 */
class UnitAlbumPhotosMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, UnitAlbumPhoto>  $photos
     */
    public function __construct(
        public UnitAlbumSend $send,
        public Collection $photos,
    ) {
        $userLocale = (string) ($send->user?->locale ?? '');
        $this->locale(in_array($userLocale, config('locales.supported', []), true)
            ? $userLocale
            : (string) config('locales.default', 'nl'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject(),
        );
    }

    public function content(): Content
    {
        $disk = Storage::disk(UnitAlbumPhoto::DISK);
        $unit = $this->send->unit;

        $images = $this->photos->values()
            ->map(fn (UnitAlbumPhoto $photo, int $index): array => [
                'data' => (string) ($disk->get($photo->path) ?? ''),
                'name' => 'foto-'.($index + 1).'.jpg',
                'mime' => (string) ($disk->mimeType($photo->path) ?: 'image/jpeg'),
            ])
            ->filter(fn (array $image): bool => $image['data'] !== '')
            ->values()
            ->all();

        return new Content(
            html: 'emails.unit-album.photos',
            with: [
                'mailSubject' => $this->mailSubject(),
                'tenantName' => $this->tenantName(),
                'unitName' => (string) ($unit?->localizedName() ?? ''),
                'dayLabel' => (string) $this->send->album_day?->copy()->locale((string) $this->locale)->translatedFormat('l j F Y'),
                'images' => $images,
            ],
        );
    }

    private function mailSubject(): string
    {
        return __('mail.unit_album.subject', ['tenant' => $this->tenantName()], $this->locale);
    }

    private function tenantName(): string
    {
        return (string) ($this->send->unit?->tenant?->name ?? config('app.name'));
    }
}
