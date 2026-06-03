<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\Location;
use App\Models\Unit;

final class LocationQrPackStickerEntries
{
    /**
     * @return list<QrStickerEntry>
     */
    public static function forLocation(Location $location): array
    {
        $location->loadMissing(['units' => fn ($q) => $q->where('is_active', true)->orderBy('name')]);
        $entries = [];

        foreach ($location->units as $unit) {
            if (! $unit instanceof Unit) {
                continue;
            }

            if (! is_string($unit->qr_token) || trim($unit->qr_token) === '') {
                continue;
            }

            $entries[] = new QrStickerEntry(
                self::stickerLabel($unit),
                UnitPortalUrl::forUnit($unit),
            );
        }

        return $entries;
    }

    private static function stickerLabel(Unit $unit): string
    {
        $name = trim((string) $unit->name) !== '' ? trim((string) $unit->name) : '—';
        $description = trim((string) ($unit->description ?? ''));

        if ($description === '') {
            return $name;
        }

        return $name.' - '.$description;
    }
}
