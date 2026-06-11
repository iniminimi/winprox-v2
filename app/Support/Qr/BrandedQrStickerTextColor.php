<?php

declare(strict_types=1);

namespace App\Support\Qr;

use GdImage;
use Imagick;
use ImagickPixel;

/**
 * Pick readable label color from artwork luminance (white on dark, dark on light).
 */
final class BrandedQrStickerTextColor
{
    /** Same as plain sticker labels (#111827). */
    private const DARK_RGB = [17, 24, 39];

    private const LIGHT_RGB = [255, 255, 255];

    private const LIGHT_BACKGROUND_LUMINANCE = 160.0;

    /**
     * Fixed dark ink for labels on the light bottom band (tenant address block).
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function darkLabelRgb(): array
    {
        return self::DARK_RGB;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgbForGdRegion(
        GdImage $canvas,
        int $centerX,
        int $centerY,
        int $sampleWidth,
        int $sampleHeight,
    ): array {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $left = max(0, $centerX - (int) round($sampleWidth / 2));
        $right = min($width - 1, $centerX + (int) round($sampleWidth / 2));
        $top = max(0, $centerY - (int) round($sampleHeight / 2));
        $bottom = min($height - 1, $centerY + (int) round($sampleHeight / 2));

        $step = 6;
        $sum = 0.0;
        $count = 0;

        for ($y = $top; $y <= $bottom; $y += $step) {
            for ($x = $left; $x <= $right; $x += $step) {
                $rgb = imagecolorat($canvas, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $sum += 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $count++;
            }
        }

        if ($count === 0) {
            return self::LIGHT_RGB;
        }

        return ($sum / $count) >= self::LIGHT_BACKGROUND_LUMINANCE
            ? self::DARK_RGB
            : self::LIGHT_RGB;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgbForImagickRegion(
        Imagick $canvas,
        int $centerX,
        int $centerY,
        int $sampleWidth,
        int $sampleHeight,
    ): array {
        $width = $canvas->getImageWidth();
        $height = $canvas->getImageHeight();
        $left = max(0, $centerX - (int) round($sampleWidth / 2));
        $right = min($width - 1, $centerX + (int) round($sampleWidth / 2));
        $top = max(0, $centerY - (int) round($sampleHeight / 2));
        $bottom = min($height - 1, $centerY + (int) round($sampleHeight / 2));

        $step = 6;
        $sum = 0.0;
        $count = 0;

        for ($y = $top; $y <= $bottom; $y += $step) {
            for ($x = $left; $x <= $right; $x += $step) {
                $pixel = $canvas->getImagePixelColor($x, $y);
                if (! $pixel instanceof ImagickPixel) {
                    continue;
                }

                $color = $pixel->getColor();
                $r = (int) ($color['r'] ?? 0);
                $g = (int) ($color['g'] ?? 0);
                $b = (int) ($color['b'] ?? 0);
                $sum += 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $count++;
            }
        }

        if ($count === 0) {
            return self::LIGHT_RGB;
        }

        return ($sum / $count) >= self::LIGHT_BACKGROUND_LUMINANCE
            ? self::DARK_RGB
            : self::LIGHT_RGB;
    }

    public static function headerSampleCenterY(): int
    {
        return Avery62x89StickerArtworkLayout::HEADER_TOP_PX
            + (int) round(Avery62x89StickerArtworkLayout::HEADER_MAX_HEIGHT_PX / 2);
    }

    public static function footerSampleCenterY(): int
    {
        return Avery62x89StickerArtworkLayout::FOOTER_TOP_PX + 18;
    }

    public static function unitCaptionSampleCenterY(): int
    {
        return Avery62x89StickerArtworkLayout::UNIT_CAPTION_TOP_PX + 14;
    }
}
