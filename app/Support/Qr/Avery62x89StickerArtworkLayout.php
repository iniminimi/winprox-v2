<?php

declare(strict_types=1);

namespace App\Support\Qr;

/**
 * QR placement on Avery 62×89-R branded artwork (@ 300 dpi, 732×1051 px = 62×89 mm).
 * Tune via proefprint; coordinates are pixel-centre + square size on the artwork file.
 */
final class Avery62x89StickerArtworkLayout
{
    public const CANVAS_WIDTH_PX = 732;

    public const CANVAS_HEIGHT_PX = 1051;

    public const DPI = 300;

    /** Square QR between the green bracket marks (approx. 36 mm). */
    public const QR_SIZE_PX = 425;

    public const QR_CENTER_X_PX = 366;

    /** Slightly above geometric centre — clears “Scan” / arrow artwork. */
    public const QR_CENTER_Y_PX = 512;

    public static function qrLeftPx(): int
    {
        return self::QR_CENTER_X_PX - (int) round(self::QR_SIZE_PX / 2);
    }

    public static function qrTopPx(): int
    {
        return self::QR_CENTER_Y_PX - (int) round(self::QR_SIZE_PX / 2);
    }

    public static function qrRenderPixelSize(): int
    {
        return max(480, self::QR_SIZE_PX);
    }
}
