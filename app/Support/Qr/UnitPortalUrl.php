<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\Unit;
/**
 * Publieke unit-meldlink voor QR-stickers en print (één unieke URL per unit).
 * Gebruikt de huidige request-host wanneer beschikbaar (zoals V1 FacilityReportUrl).
 */
final class UnitPortalUrl
{
    public static function forUnit(Unit $unit): string
    {
        $token = self::ensureQrToken($unit);

        return url(route('public.unit-portal', ['token' => $token], absolute: false));
    }

    public static function ensureQrToken(Unit $unit): string
    {
        $token = is_string($unit->qr_token) ? trim($unit->qr_token) : '';

        if ($token !== '') {
            return $token;
        }

        $token = Unit::generateUniqueQrToken();
        $unit->forceFill(['qr_token' => $token])->saveQuietly();

        return $token;
    }
}
