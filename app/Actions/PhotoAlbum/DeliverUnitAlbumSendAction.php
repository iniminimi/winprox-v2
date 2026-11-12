<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Enums\UnitAlbumSendStatus;
use App\Events\OutgoingMailBlockedByUnsubscribe;
use App\Mail\UnitAlbumPhotosMail;
use App\Models\EmailUnsubscribe;
use App\Models\UnitAlbumPhoto;
use App\Models\UnitAlbumSend;
use App\Support\Audit\AuditRecorder;
use App\Support\EmailUnsubscribeExemptions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Verstuurt één geplande albummail. Afgemeld adres → status
 * BlockedUnsubscribed (zichtbaar bij de verzendingen in beheer).
 */
final class DeliverUnitAlbumSendAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(int $unitAlbumSendId): ?UnitAlbumSend
    {
        $send = UnitAlbumSend::query()
            ->withoutGlobalScope('tenant')
            ->with(['unit.tenant', 'user'])
            ->find($unitAlbumSendId);

        if ($send === null || $send->status !== UnitAlbumSendStatus::Queued) {
            return $send;
        }

        $email = (string) $send->recipient_email;
        if (! EmailUnsubscribeExemptions::isExempt($email) && EmailUnsubscribe::isUnsubscribed($email)) {
            return $this->finish($send, UnitAlbumSendStatus::BlockedUnsubscribed);
        }

        $disk = Storage::disk(UnitAlbumPhoto::DISK);
        $photos = UnitAlbumPhoto::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $send->tenant_id)
            ->where('unit_id', $send->unit_id)
            ->whereIn('id', $send->photo_ids ?? [])
            ->orderBy('id')
            ->get()
            ->filter(fn (UnitAlbumPhoto $photo): bool => $disk->exists((string) $photo->path))
            ->values();

        if ($photos->isEmpty() || $send->unit === null) {
            return $this->finish($send, UnitAlbumSendStatus::Failed);
        }

        $blocked = false;
        Event::listen(OutgoingMailBlockedByUnsubscribe::class, static function () use (&$blocked): void {
            $blocked = true;
        });

        try {
            Mail::to($email)->send(new UnitAlbumPhotosMail($send, $photos));
        } catch (\Throwable $e) {
            Log::warning('unit_album_send_failed', [
                'unit_album_send_id' => (int) $send->id,
                'error' => $e->getMessage(),
            ]);

            return $this->finish($send, UnitAlbumSendStatus::Failed);
        }

        return $this->finish($send, $blocked ? UnitAlbumSendStatus::BlockedUnsubscribed : UnitAlbumSendStatus::Sent);
    }

    private function finish(UnitAlbumSend $send, UnitAlbumSendStatus $status): UnitAlbumSend
    {
        $send->status = $status;
        $send->sent_at = $status === UnitAlbumSendStatus::Sent ? now() : null;
        $send->save();

        $this->audit->record(
            userId: $send->user_id !== null ? (int) $send->user_id : null,
            tenantId: (int) $send->tenant_id,
            action: 'unit_album.send_'.$status->value,
            modelType: UnitAlbumSend::class,
            modelId: (int) $send->id,
            payload: [
                'unit_id' => (int) $send->unit_id,
                'photo_count' => (int) $send->photo_count,
            ],
        );

        return $send;
    }
}
