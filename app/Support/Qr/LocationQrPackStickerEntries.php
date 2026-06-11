<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\Location;
use App\Models\QrCode;
use App\Models\Unit;

final class LocationQrPackStickerEntries
{
    /**
     * @return list<QrStickerEntry>
     */
    public static function forLocation(Location $location): array
    {
        $location->loadMissing([
            'units' => fn ($q) => $q->where('is_active', true)->orderBy('name')->with('qrCodes'),
        ]);
        $entries = [];

        foreach ($location->units as $unit) {
            if (! $unit instanceof Unit) {
                continue;
            }

            if (! is_string($unit->qr_token) || trim($unit->qr_token) === '') {
                continue;
            }

            $stickerNumber = self::stickerNumberForUnit($unit);

            $entries[] = new QrStickerEntry(
                unitLabel: $stickerNumber ?? self::stickerLabel($unit),
                reportUrl: UnitPortalUrl::forUnit($unit),
                headerFallback: self::portalHeaderFallback($unit, $location),
                stickerNumber: $stickerNumber,
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

    private static function portalHeaderFallback(Unit $unit, Location $location): string
    {
        $locationName = trim((string) $location->name);
        $unitName = trim((string) $unit->name) !== '' ? trim((string) $unit->name) : '—';
        $line1 = $locationName !== '' ? $locationName.' · '.$unitName : $unitName;
        $description = trim((string) ($unit->description ?? ''));

        if ($description === '') {
            return $line1;
        }

        return $line1."\n".$description;
    }

    private static function stickerNumberForUnit(Unit $unit): ?string
    {
        $qrCode = $unit->qrCodes
            ->sortBy(fn (QrCode $qr) => $qr->id)
            ->first();

        if (! $qrCode instanceof QrCode) {
            return null;
        }

        $number = trim($qrCode->display_sticker_number);

        return $number !== '' ? $number : null;
    }
}
