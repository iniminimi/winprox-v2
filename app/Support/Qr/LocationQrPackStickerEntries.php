<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Actions\QrCodes\EnsureUnitStickerQrCodeAction;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\Unit;

final class LocationQrPackStickerEntries
{
    /**
     * @return list<QrStickerEntry>
     */
    public static function forLocation(
        Location $location,
        ?EnsureUnitStickerQrCodeAction $ensureStickerQr = null,
        ?int $actorUserId = null,
    ): array {
        $location->loadMissing([
            'units' => fn ($q) => $q->where('is_active', true)->orderBy('name')->with('qrCodes'),
        ]);
        $entries = [];

        foreach ($location->units as $unit) {
            if (! $unit instanceof Unit) {
                continue;
            }

            $entry = self::entryForUnit($unit, $location, $ensureStickerQr, $actorUserId);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return list<QrStickerEntry>
     */
    public static function forUnit(
        Unit $unit,
        ?EnsureUnitStickerQrCodeAction $ensureStickerQr = null,
        ?int $actorUserId = null,
    ): array {
        $unit->loadMissing(['location', 'qrCodes']);
        $location = $unit->location;

        if (! $location instanceof Location) {
            return [];
        }

        $entry = self::entryForUnit($unit, $location, $ensureStickerQr, $actorUserId);

        return $entry !== null ? [$entry] : [];
    }

    private static function entryForUnit(
        Unit $unit,
        Location $location,
        ?EnsureUnitStickerQrCodeAction $ensureStickerQr = null,
        ?int $actorUserId = null,
    ): ?QrStickerEntry {
        if (! is_string($unit->qr_token) || trim($unit->qr_token) === '') {
            return null;
        }

        if ($ensureStickerQr !== null) {
            $qrCode = $ensureStickerQr->handle($unit, (int) $location->tenant_id, $actorUserId);
            $stickerNumber = trim((string) $qrCode->display_sticker_number);
            $reportUrl = route('qr.scan', ['token' => $qrCode->token]);
        } else {
            $stickerNumber = self::stickerNumberForUnit($unit) ?? '';
            $reportUrl = UnitPortalUrl::forUnit($unit);
        }

        return new QrStickerEntry(
            unitLabel: $stickerNumber !== '' ? $stickerNumber : self::stickerLabel($unit),
            reportUrl: $reportUrl,
            headerFallback: self::portalHeaderFallback($unit, $location),
            stickerNumber: $stickerNumber !== '' ? $stickerNumber : null,
            locationUnitLabel: self::locationUnitCaption($location, $unit),
        );
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
        $line1 = self::locationUnitCaption($location, $unit, separator: ' · ');
        $description = trim((string) ($unit->description ?? ''));

        if ($description === '') {
            return $line1;
        }

        return $line1."\n".$description;
    }

    private static function locationUnitCaption(Location $location, Unit $unit, string $separator = ' - '): string
    {
        $locationName = trim((string) $location->name);
        $unitName = trim((string) $unit->name) !== '' ? trim((string) $unit->name) : '—';

        return $locationName !== '' ? $locationName.$separator.$unitName : $unitName;
    }

    private static function stickerNumberForUnit(Unit $unit): ?string
    {
        $qrCode = $unit->qrCodes
            ->sortBy(fn (QrCode $qr) => $qr->id)
            ->first();

        if (! $qrCode instanceof QrCode) {
            return null;
        }

        $number = trim((string) $qrCode->display_sticker_number);

        return $number !== '' ? $number : null;
    }
}
