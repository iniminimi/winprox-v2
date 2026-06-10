<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Actions\Units\EnsureUnitQrTokenAction;
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
        return app(EnsureUnitQrTokenAction::class)->handle($unit);
    }
}
