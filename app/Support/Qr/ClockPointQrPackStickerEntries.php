<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\ClockPoint;

final class ClockPointQrPackStickerEntries
{
    /**
     * @return list<QrStickerEntry>
     */
    public static function forClockPoint(ClockPoint $clockPoint): array
    {
        $clockPoint->loadMissing('location');

        $name = trim((string) $clockPoint->name);
        if ($name === '') {
            $name = '—';
        }

        $locationName = '';
        if ($clockPoint->location !== null) {
            $locationName = trim((string) $clockPoint->location->localizedName());
        }

        return [
            new QrStickerEntry(
                unitLabel: $name,
                reportUrl: $clockPoint->portalUrl(),
                stickerNumber: $locationName !== '' ? $locationName : '',
                locationUnitLabel: $name,
            ),
        ];
    }
}
