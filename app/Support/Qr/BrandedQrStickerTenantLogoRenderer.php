<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Enums\QrStickerTenantLogoPlacement;
use GdImage;
use Imagick;

/** Tenant organisation logo on the sticker artwork (never inside the QR code). */
final class BrandedQrStickerTenantLogoRenderer
{
    /**
     * @param  GdImage|resource  $canvas
     */
    public static function drawOnGd($canvas, string $logoPath, QrStickerTenantLogoPlacement $placement): void
    {
        if ($placement === QrStickerTenantLogoPlacement::None || ! is_file($logoPath)) {
            return;
        }

        $logo = self::loadGdImageFromFile($logoPath);
        if ($logo === false) {
            return;
        }

        [$targetW, $targetH] = self::fitSize(imagesx($logo), imagesy($logo));
        $resized = imagecreatetruecolor($targetW, $targetH);
        if ($resized === false) {
            imagedestroy($logo);

            return;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefill($resized, 0, 0, $transparent);
        }

        imagecopyresampled($resized, $logo, 0, 0, 0, 0, $targetW, $targetH, imagesx($logo), imagesy($logo));
        imagedestroy($logo);

        [$destX, $destY] = self::destination($placement, $targetW, $targetH);

        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);
        imagecopyresampled($canvas, $resized, $destX, $destY, 0, 0, $targetW, $targetH, $targetW, $targetH);
        imagedestroy($resized);
    }

    public static function drawOnImagick(Imagick $canvas, string $logoPath, QrStickerTenantLogoPlacement $placement): void
    {
        if ($placement === QrStickerTenantLogoPlacement::None || ! is_file($logoPath)) {
            return;
        }

        $logo = new Imagick;
        if (str_ends_with(strtolower($logoPath), '.svg')) {
            $logo->setBackgroundColor(new \ImagickPixel('transparent'));
        }

        try {
            $logo->readImage($logoPath);
        } catch (\Throwable) {
            $logo->clear();

            return;
        }

        $logo->resizeImage(
            Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_WIDTH_PX,
            Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_HEIGHT_PX,
            Imagick::FILTER_LANCZOS,
            1,
            true,
        );

        [$destX, $destY] = self::destination(
            $placement,
            $logo->getImageWidth(),
            $logo->getImageHeight(),
        );

        $canvas->compositeImage($logo, Imagick::COMPOSITE_OVER, $destX, $destY);
        $logo->clear();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function destination(QrStickerTenantLogoPlacement $placement, int $width, int $height): array
    {
        return match ($placement) {
            QrStickerTenantLogoPlacement::TopLeft => [
                Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_LEFT_PX,
                Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_TOP_PX,
            ],
            QrStickerTenantLogoPlacement::TopRight => [
                Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX
                    - Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_RIGHT_PX
                    - $width,
                Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_TOP_PX,
            ],
            QrStickerTenantLogoPlacement::BottomLeft => [
                Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_LEFT_PX,
                Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX
                    - Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_BOTTOM_PX
                    - $height,
            ],
            QrStickerTenantLogoPlacement::BottomRight => [
                Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX
                    - Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_RIGHT_PX
                    - $width,
                Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX
                    - Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_BOTTOM_PX
                    - $height,
            ],
            QrStickerTenantLogoPlacement::None => [0, 0],
        };
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function fitSize(int $width, int $height): array
    {
        $maxW = Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_WIDTH_PX;
        $maxH = Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_HEIGHT_PX;
        $scale = min($maxW / max(1, $width), $maxH / max(1, $height));

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }

    private static function loadGdImageFromFile(string $absolutePath): GdImage|false
    {
        if (str_ends_with(strtolower($absolutePath), '.svg')) {
            return self::rasterizeSvgWithGd($absolutePath);
        }

        $binary = file_get_contents($absolutePath);
        if ($binary === false || $binary === '') {
            return false;
        }

        return @imagecreatefromstring($binary);
    }

    private static function rasterizeSvgWithGd(string $svgPath): GdImage|false
    {
        if (! class_exists(Imagick::class)) {
            return false;
        }

        try {
            $imagick = new Imagick;
            $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
            $imagick->readImage($svgPath);
            $imagick->setImageFormat('png');
            $image = @imagecreatefromstring($imagick->getImageBlob());
            $imagick->clear();

            return $image;
        } catch (\Throwable) {
            return false;
        }
    }
}
