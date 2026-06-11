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

    /** Upper black band — unit label left-aligned, clears Prox logo (top-right). */
    public const HEADER_PADDING_LEFT_PX = 36;

    public const HEADER_PADDING_RIGHT_PX = 200;

    public const HEADER_TOP_PX = 72;

    public const HEADER_MAX_HEIGHT_PX = 140;

    public const HEADER_MAX_FONT_SIZE_PX = 42;

    public const HEADER_MIN_FONT_SIZE_PX = 22;

    public const HEADER_LINE_HEIGHT_RATIO = 1.15;

    public const HEADER_MAX_LINES = 2;

    /** @var array{0: int, 1: int, 2: int} */
    public const HEADER_COLOR_RGB = [255, 255, 255];

    public static function headerMaxWidthPx(): int
    {
        return self::CANVAS_WIDTH_PX - self::HEADER_PADDING_LEFT_PX - self::HEADER_PADDING_RIGHT_PX;
    }

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
