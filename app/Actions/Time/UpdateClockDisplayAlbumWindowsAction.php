<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Album-vensters van het klokscherm: max. 2 vanaf/tot-paren. Tijdens een
 * venster toont het scherm de foto-slideshow i.p.v. de QR-klok.
 * Beide leeg per paar = venster uit; over-middernacht werkt zoals aan-uren.
 */
class UpdateClockDisplayAlbumWindowsAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        ClockPoint $clockPoint,
        int $tenantId,
        ?int $actorUserId,
        ?string $from1,
        ?string $until1,
        ?string $from2,
        ?string $until2,
    ): ClockPoint {
        if ((int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if (($from1 === null) !== ($until1 === null)
            || ($from2 === null) !== ($until2 === null)) {
            throw new InvalidArgumentException('album_windows_incomplete');
        }

        return DB::transaction(function () use ($clockPoint, $tenantId, $actorUserId, $from1, $until1, $from2, $until2) {
            $locked = ClockPoint::query()
                ->whereKey($clockPoint->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new InvalidArgumentException('clock_point_not_found');
            }

            $locked->update([
                'album1_from' => $from1,
                'album1_until' => $until1,
                'album2_from' => $from2,
                'album2_until' => $until2,
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_point.album_windows_updated',
                modelType: ClockPoint::class,
                modelId: $locked->id,
                payload: [
                    'clock_point_id' => $locked->id,
                    'album1' => [$from1, $until1],
                    'album2' => [$from2, $until2],
                ],
            );

            return $locked;
        });
    }
}
