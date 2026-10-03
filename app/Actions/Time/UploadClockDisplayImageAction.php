<?php

namespace App\Actions\Time;

use App\Models\ClockDisplayImage;
use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Voegt één foto toe aan het slideshow-album van het klokscherm.
 * Het bestand is client-side al vierkant (1:1) en ~480px gecomprimeerd —
 * geen server-resize (WINPROX_RULES.md §7). `clockPoint = null` = globaal
 * ("toon op alle clockpoints").
 */
class UploadClockDisplayImageAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        UploadedFile $file,
        ?ClockPoint $clockPoint,
        int $tenantId,
        ?int $actorUserId,
    ): ClockDisplayImage {
        if ($clockPoint !== null && (int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $scopeCount = ClockDisplayImage::query()
            ->where('tenant_id', $tenantId)
            ->when(
                $clockPoint !== null,
                fn ($q) => $q->where('clock_point_id', $clockPoint->id),
                fn ($q) => $q->whereNull('clock_point_id'),
            )
            ->count();

        if ($scopeCount >= ClockDisplayImage::MAX_PER_SCOPE) {
            throw new InvalidArgumentException('album_full');
        }

        return DB::transaction(function () use ($file, $clockPoint, $tenantId, $actorUserId) {
            $path = $file->store(
                'clock-display-album/'.($clockPoint?->id ?? 'global'),
                'public',
            );

            if ($path === false) {
                throw new InvalidArgumentException('upload_failed');
            }

            $image = ClockDisplayImage::create([
                'tenant_id' => $tenantId,
                'clock_point_id' => $clockPoint?->id,
                'path' => $path,
                'sort_order' => (int) ClockDisplayImage::query()
                    ->where('tenant_id', $tenantId)
                    ->max('sort_order') + 1,
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_display.image_uploaded',
                modelType: ClockDisplayImage::class,
                modelId: $image->id,
                payload: [
                    'clock_point_id' => $clockPoint?->id,
                    'all_clock_points' => $clockPoint === null,
                ],
            );

            return $image;
        });
    }
}
