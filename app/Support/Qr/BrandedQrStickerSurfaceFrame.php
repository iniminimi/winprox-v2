<?php

declare(strict_types=1);

namespace App\Support\Qr;

use GdImage;
use Imagick;
use ImagickDraw;
use ImagickPixel;

/** White rounded box with thin dark border — matches `.wp-sidebar-header-logo`. */
final class BrandedQrStickerSurfaceFrame
{
    private const FILL_RGB = [255, 255, 255];

    /** Same visual weight as `--wp-border` on white, but black for print contrast. */
    private const BORDER_RGB = [0, 0, 0];

    public static function borderPx(): int
    {
        return Avery62x89StickerArtworkLayout::TENANT_SURFACE_BORDER_PX;
    }

    public static function radiusPx(): int
    {
        return Avery62x89StickerArtworkLayout::TENANT_SURFACE_RADIUS_PX;
    }

    public static function paddingPx(): int
    {
        return Avery62x89StickerArtworkLayout::TENANT_SURFACE_PADDING_PX;
    }

    public static function contentInsetPx(): int
    {
        return self::borderPx() + self::paddingPx();
    }

    public static function horizontalOverheadPx(): int
    {
        return self::contentInsetPx() * 2;
    }

    public static function verticalOverheadPx(): int
    {
        return self::contentInsetPx() * 2;
    }

    /**
     * @param  GdImage|resource  $canvas
     */
    public static function drawOnGd($canvas, int $x, int $y, int $width, int $height): void
    {
        if ($width <= 0 || $height <= 0) {
            return;
        }

        $border = self::borderPx();
        $radius = min(self::radiusPx(), (int) floor(min($width, $height) / 2));

        [$br, $bg, $bb] = self::BORDER_RGB;
        $borderColor = imagecolorallocate($canvas, $br, $bg, $bb);
        if ($borderColor === false) {
            throw new \RuntimeException('Unable to allocate branded sticker surface border color.');
        }

        [$fr, $fg, $fb] = self::FILL_RGB;
        $fillColor = imagecolorallocate($canvas, $fr, $fg, $fb);
        if ($fillColor === false) {
            throw new \RuntimeException('Unable to allocate branded sticker surface fill color.');
        }

        self::fillRoundedRectGd($canvas, $x, $y, $width, $height, $radius, $borderColor);

        $innerX = $x + $border;
        $innerY = $y + $border;
        $innerW = $width - ($border * 2);
        $innerH = $height - ($border * 2);
        if ($innerW <= 0 || $innerH <= 0) {
            return;
        }

        $innerRadius = max(0, $radius - $border);
        self::fillRoundedRectGd($canvas, $innerX, $innerY, $innerW, $innerH, $innerRadius, $fillColor);
    }

    public static function drawOnImagick(Imagick $canvas, int $x, int $y, int $width, int $height): void
    {
        if ($width <= 0 || $height <= 0) {
            return;
        }

        $radius = min((float) self::radiusPx(), min($width, $height) / 2.0);

        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel(sprintf(
            'rgb(%d,%d,%d)',
            self::FILL_RGB[0],
            self::FILL_RGB[1],
            self::FILL_RGB[2],
        )));
        $draw->setStrokeColor(new ImagickPixel(sprintf(
            'rgb(%d,%d,%d)',
            self::BORDER_RGB[0],
            self::BORDER_RGB[1],
            self::BORDER_RGB[2],
        )));
        $draw->setStrokeWidth((float) self::borderPx());
        $draw->roundRectangle(
            (float) $x,
            (float) $y,
            (float) ($x + $width),
            (float) ($y + $height),
            $radius,
            $radius,
        );

        $canvas->drawImage($draw);
    }

    /**
     * @param  GdImage|resource  $canvas
     */
    private static function fillRoundedRectGd($canvas, int $x, int $y, int $width, int $height, int $radius, int $color): void
    {
        if ($width <= 0 || $height <= 0) {
            return;
        }

        if ($radius <= 0 || $width <= $radius * 2 || $height <= $radius * 2) {
            imagefilledrectangle($canvas, $x, $y, $x + $width - 1, $y + $height - 1, $color);

            return;
        }

        imagefilledrectangle($canvas, $x + $radius, $y, $x + $width - $radius - 1, $y + $height - 1, $color);
        imagefilledrectangle($canvas, $x, $y + $radius, $x + $width - 1, $y + $height - $radius - 1, $color);

        imagefilledellipse($canvas, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $x + $width - $radius - 1, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $x + $radius, $y + $height - $radius - 1, $radius * 2, $radius * 2, $color);
        imagefilledellipse(
            $canvas,
            $x + $width - $radius - 1,
            $y + $height - $radius - 1,
            $radius * 2,
            $radius * 2,
            $color,
        );
    }
}
