<?php

declare(strict_types=1);

namespace App\Support\Qr;

/**
 * Promo-label voor stickernummers (canoniek in DB: YYMM-##### of legacy QR-YYYY-#####).
 */
final class QrStickerNumber
{
    public const BRAND_PREFIX = 'Winprox';

    public static function display(?string $canonical): string
    {
        if ($canonical === null || trim($canonical) === '') {
            return '';
        }

        $canonical = trim($canonical);
        $brandPrefix = self::BRAND_PREFIX.'-';

        if (str_starts_with(strtolower($canonical), strtolower($brandPrefix))) {
            return $canonical;
        }

        return $brandPrefix.$canonical;
    }
}
