<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Album-vensters van het klokscherm: max. 2 vanaf/tot-paren. Tijdens een
 * venster toont het scherm het gekozen rust-scherm i.p.v. de QR-klok:
 * foto-slideshow (photos), wijzerklok (clock) of niets (none).
 * Beide leeg per paar = venster uit; over-middernacht werkt zoals aan-uren.
 * `album_days` = bitmask weekdagen (bit 0 = ma … bit 6 = zo) waarop het
 * rust-scherm actief is; default 31 = ma–vr (weekend uit).
 */
class UpdateClockDisplayAlbumWindowsAction
{
    public const MODES = ['photos', 'clock', 'none'];

    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        ClockPoint $clockPoint,
        int $tenantId,
        ?int $actorUserId,
        ?string $from1,
        ?string $until1,
        ?string $from2,
        ?string $until2,
        string $albumMode = 'photos',
        int $albumDays = 31,
    ): ClockPoint {
        if ((int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if (! in_array($albumMode, self::MODES, true)) {
            throw new InvalidArgumentException('album_mode_invalid');
        }

        if ($albumDays < 0 || $albumDays > 0x7F) {
            throw new InvalidArgumentException('album_days_invalid');
        }

        if (($from1 === null) !== ($until1 === null)
            || ($from2 === null) !== ($until2 === null)) {
            throw new InvalidArgumentException('album_windows_incomplete');
        }

        // Rust-vensters mogen niet buiten de schermuren vallen — het scherm
        // is dan toch uit, dus zo'n venster zou nooit zichtbaar zijn.
        $onFrom = $this->hm($clockPoint->display_on_from);
        $onUntil = $this->hm($clockPoint->display_on_until);
        foreach ([[$from1, $until1], [$from2, $until2]] as [$af, $au]) {
            if ($af !== null && ! $this->withinDisplayHours($onFrom, $onUntil, $af, $au)) {
                throw new InvalidArgumentException('album_outside_schedule');
            }
        }

        return DB::transaction(function () use ($clockPoint, $tenantId, $actorUserId, $from1, $until1, $from2, $until2, $albumMode, $albumDays) {
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
                'album_mode' => $albumMode,
                'album_days' => $albumDays,
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
                    'album_mode' => $albumMode,
                    'album_days' => $albumDays,
                ],
            );

            return $locked;
        });
    }

    private function hm($time): ?string
    {
        return $time !== null ? substr((string) $time, 0, 5) : null;
    }

    /**
     * Liggen vanaf/tot volledig binnen de aan-uren? Geen aan-uren ingesteld
     * (altijd aan) = geen grens. Over-middernacht werkt in beide richtingen:
     * een nachtscherm 22:00–06:00 laat bv. album 23:00–05:00 toe.
     */
    private function withinDisplayHours(?string $onFrom, ?string $onUntil, string $from, string $until): bool
    {
        if ($onFrom === null || $onUntil === null) {
            return true;
        }

        $overnight = $onFrom > $onUntil;
        $inWindow = fn (string $t) => $overnight
            ? ($t >= $onFrom || $t <= $onUntil)
            : ($t >= $onFrom && $t <= $onUntil);

        if (! $inWindow($from) || ! $inWindow($until)) {
            return false;
        }

        if ($from > $until) {
            // Album over middernacht mag enkel als het scherm dat ook is,
            // en dan binnen hetzelfde nachtvenster.
            return $overnight && $from >= $onFrom && $until <= $onUntil;
        }

        // Dagvenster binnen een nachtscherm: volledig in één deel van het venster.
        return ! $overnight || $from >= $onFrom || $until <= $onUntil;
    }
}
