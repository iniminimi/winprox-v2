<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Data\PhotoAlbum\SendUnitAlbumPhotosData;
use App\Enums\UnitAlbumSendStatus;
use App\Jobs\SendUnitAlbumMailJob;
use App\Models\EmailUnsubscribe;
use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use App\Models\UnitAlbumRecipient;
use App\Models\UnitAlbumSend;
use App\Support\Audit\AuditRecorder;
use App\Support\EmailUnsubscribeExemptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plant de albumfoto's in voor precies één ontvanger: per
 * `max_photos_per_mail` foto's één mail. Bewaart het adres bij de unit.
 * Uurlimieten per tenant en globaal (Cloud86: 250/u per hostingpakket):
 * overschot wacht in de queue tot er weer plaats is.
 */
final class SendUnitAlbumPhotosAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return Collection<int, UnitAlbumSend>
     */
    public function handle(SendUnitAlbumPhotosData $data, int $tenantId, int $actorUserId): Collection
    {
        $unit = Unit::query()->where('tenant_id', $tenantId)->find($data->unitId);
        if ($unit === null || ! $unit->allowsPhotoAlbum()) {
            throw ValidationException::withMessages([
                'unit_id' => [__('photo_album.errors.unit_unavailable')],
            ]);
        }

        $photos = UnitAlbumPhoto::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unit->id)
            ->whereIn('id', $data->photoIds)
            ->orderBy('id')
            ->get();

        if ($photos->isEmpty() || $photos->count() !== count($data->photoIds)) {
            throw ValidationException::withMessages([
                'photo_ids' => [__('photo_album.errors.photos_unavailable')],
            ]);
        }

        $email = EmailUnsubscribe::normalizeEmail($data->email);
        if (! EmailUnsubscribeExemptions::isExempt($email) && EmailUnsubscribe::isUnsubscribed($email)) {
            throw ValidationException::withMessages([
                'email' => [__('photo_album.errors.unsubscribed', ['email' => $email])],
            ]);
        }

        $sends = DB::transaction(function () use ($unit, $photos, $email, $data, $tenantId, $actorUserId): Collection {
            $recipient = UnitAlbumRecipient::query()->firstOrNew([
                'tenant_id' => $tenantId,
                'unit_id' => $unit->id,
                'email' => $email,
            ]);
            if ($data->recipientDescription !== null) {
                $recipient->description = $data->recipientDescription;
            }
            $recipient->last_sent_at = now();
            $recipient->save();

            $albumDay = $photos->min(fn (UnitAlbumPhoto $photo) => $photo->taken_on?->toDateString());

            return $photos
                ->chunk(self::maxPerMail())
                ->map(function (Collection $chunk) use ($unit, $email, $albumDay, $tenantId, $actorUserId): UnitAlbumSend {
                    $send = UnitAlbumSend::query()->create([
                        'tenant_id' => $tenantId,
                        'unit_id' => $unit->id,
                        'user_id' => $actorUserId,
                        'recipient_email' => $email,
                        'photo_ids' => $chunk->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                        'photo_count' => $chunk->count(),
                        'album_day' => $albumDay,
                        'status' => UnitAlbumSendStatus::Queued,
                        'scheduled_at' => $this->nextSlot($tenantId),
                    ]);

                    $this->audit->record(
                        userId: $actorUserId,
                        tenantId: $tenantId,
                        action: 'unit_album.send_queued',
                        modelType: UnitAlbumSend::class,
                        modelId: (int) $send->id,
                        payload: [
                            'unit_id' => (int) $unit->id,
                            'photo_count' => $chunk->count(),
                            'scheduled_at' => $send->scheduled_at?->toIso8601String(),
                        ],
                    );

                    return $send;
                })
                ->values();
        });

        foreach ($sends as $send) {
            $job = new SendUnitAlbumMailJob((int) $send->id);
            if ($send->scheduled_at !== null && $send->scheduled_at->isFuture()) {
                dispatch($job->delay($send->scheduled_at));
            } else {
                dispatch($job);
            }
        }

        return $sends;
    }

    public static function maxPerMail(): int
    {
        return max(1, (int) config('unit_photo_album.max_photos_per_mail', 8));
    }

    /**
     * Vroegste tijdstip waarop er in het voorbije uur minder mails gepland
     * staan dan de limiet — per tenant en voor alle tenants samen.
     */
    private function nextSlot(int $tenantId): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $slot = $now;

        $limits = [
            [$tenantId, max(1, (int) config('unit_photo_album.mails_per_hour_per_tenant', 25))],
            [null, max(1, (int) config('unit_photo_album.mails_per_hour_global', 150))],
        ];

        foreach ($limits as [$scopeTenantId, $limit]) {
            $times = UnitAlbumSend::query()
                ->withoutGlobalScope('tenant')
                ->when($scopeTenantId !== null, fn ($q) => $q->where('tenant_id', $scopeTenantId))
                ->whereIn('status', [UnitAlbumSendStatus::Queued->value, UnitAlbumSendStatus::Sent->value])
                ->where('scheduled_at', '>', $now->subHour())
                ->orderByDesc('scheduled_at')
                ->limit($limit)
                ->pluck('scheduled_at');

            if ($times->count() >= $limit) {
                $earliest = CarbonImmutable::parse($times->last())->addHour();
                if ($earliest->greaterThan($slot)) {
                    $slot = $earliest;
                }
            }
        }

        return $slot;
    }
}
