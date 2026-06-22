<?php

declare(strict_types=1);

namespace App\Support\Qr;

use GdImage;
use Imagick;
use ImagickPixel;

/** Tenant logo raster for sticker overlays — alpha-safe on GD and Imagick canvases. */
final class BrandedQrStickerLogoRaster
{
    /**
     * @param  GdImage|resource  $canvas
     */
    public static function compositeOnGd(
        $canvas,
        string $logoPath,
        int $x,
        int $y,
        int $width,
        int $height,
    ): void {
        $overlay = self::rasterGdOverlay($logoPath, $width, $height);
        if ($overlay === false) {
            return;
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);
        imagecopy($canvas, $overlay, $x, $y, 0, 0, $width, $height);
        imagedestroy($overlay);
    }

    public static function compositeOnImagick(
        Imagick $canvas,
        string $logoPath,
        int $x,
        int $y,
        int $width,
        int $height,
    ): void {
        if (extension_loaded('gd')) {
            self::compositeOnImagickViaGd($canvas, $logoPath, $x, $y, $width, $height);

            return;
        }

        self::compositeOnImagickNative($canvas, $logoPath, $x, $y, $width, $height);
    }

    public static function rasterGdOverlay(string $logoPath, int $width, int $height): GdImage|false
    {
        $source = QrStickerRasterCache::gdSource($logoPath);
        if ($source === false) {
            return false;
        }

        $overlay = self::resizePreservingAlphaGd($source, $width, $height);
        imagedestroy($source);

        return $overlay;
    }

    private static function compositeOnImagickViaGd(
        Imagick $canvas,
        string $logoPath,
        int $x,
        int $y,
        int $width,
        int $height,
    ): void {
        $overlay = self::rasterGdOverlay($logoPath, $width, $height);
        if ($overlay === false) {
            return;
        }

        ob_start();
        imagepng($overlay);
        $bytes = ob_get_clean();
        imagedestroy($overlay);

        if ($bytes === false || $bytes === '') {
            return;
        }

        $image = new Imagick;
        $image->readImageBlob($bytes);
        $canvas->compositeImage($image, Imagick::COMPOSITE_OVER, $x, $y);
        $image->clear();
    }

    private static function compositeOnImagickNative(
        Imagick $canvas,
        string $logoPath,
        int $x,
        int $y,
        int $width,
        int $height,
    ): void {
        $cachedLogo = QrStickerRasterCache::imagickSource($logoPath);
        if ($cachedLogo === null) {
            return;
        }

        $image = clone $cachedLogo;
        $image->setImageFormat('png');
        $image->setImageBackgroundColor(new ImagickPixel('transparent'));
        $image->setImageType(Imagick::IMGTYPE_TRUECOLORALPHA);
        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        $image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
        $canvas->compositeImage($image, Imagick::COMPOSITE_OVER, $x, $y);
        $image->clear();
    }

    private static function resizePreservingAlphaGd(GdImage $logo, int $targetW, int $targetH): GdImage|false
    {
        $resized = imagecreatetruecolor($targetW, $targetH);
        if ($resized === false) {
            return false;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        if ($transparent === false) {
            imagedestroy($resized);

            return false;
        }

        imagefill($resized, 0, 0, $transparent);
        imagealphablending($resized, true);
        imagesavealpha($resized, true);
        imagealphablending($logo, true);
        imagesavealpha($logo, true);
        imagecopyresampled(
            $resized,
            $logo,
            0,
            0,
            0,
            0,
            $targetW,
            $targetH,
            imagesx($logo),
            imagesy($logo),
        );

        return $resized;
    }
}
