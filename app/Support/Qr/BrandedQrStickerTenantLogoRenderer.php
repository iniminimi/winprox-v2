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

        $logo = QrStickerRasterCache::gdSource($logoPath);
        if ($logo === false) {
            return;
        }

        [$targetW, $targetH] = self::fitSize(imagesx($logo), imagesy($logo));
        $resized = self::resizeLogoPreservingAlphaGd($logo, $targetW, $targetH);
        imagedestroy($logo);
        if ($resized === false) {
            return;
        }

        $frame = self::frameLayout($placement, $targetW, $targetH);

        BrandedQrStickerSurfaceFrame::drawOnGd(
            $canvas,
            $frame['box_x'],
            $frame['box_y'],
            $frame['box_width'],
            $frame['box_height'],
        );

        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);
        imagecopy(
            $canvas,
            $resized,
            $frame['logo_x'],
            $frame['logo_y'],
            0,
            0,
            $targetW,
            $targetH,
        );
        imagedestroy($resized);
    }

    public static function drawOnImagick(Imagick $canvas, string $logoPath, QrStickerTenantLogoPlacement $placement): void
    {
        if ($placement === QrStickerTenantLogoPlacement::None || ! is_file($logoPath)) {
            return;
        }

        $cachedLogo = QrStickerRasterCache::imagickSource($logoPath);
        if ($cachedLogo === null) {
            return;
        }

        $logo = clone $cachedLogo;

        $maxLogoW = Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_WIDTH_PX
            - BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $maxLogoH = Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_HEIGHT_PX
            - BrandedQrStickerSurfaceFrame::verticalOverheadPx();

        $logo->resizeImage(
            max(1, $maxLogoW),
            max(1, $maxLogoH),
            Imagick::FILTER_LANCZOS,
            1,
            true,
        );
        $logo->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

        $frame = self::frameLayout(
            $placement,
            $logo->getImageWidth(),
            $logo->getImageHeight(),
        );

        BrandedQrStickerSurfaceFrame::drawOnImagick(
            $canvas,
            $frame['box_x'],
            $frame['box_y'],
            $frame['box_width'],
            $frame['box_height'],
        );

        $canvas->compositeImage($logo, Imagick::COMPOSITE_OVER, $frame['logo_x'], $frame['logo_y']);
        $logo->clear();
    }

    /**
     * @return array{
     *     box_x: int,
     *     box_y: int,
     *     box_width: int,
     *     box_height: int,
     *     logo_x: int,
     *     logo_y: int
     * }
     */
    private static function frameLayout(QrStickerTenantLogoPlacement $placement, int $logoWidth, int $logoHeight): array
    {
        $boxWidth = $logoWidth + BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $boxHeight = $logoHeight + BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        [$boxX, $boxY] = self::boxOrigin($placement, $boxWidth, $boxHeight);
        $inset = BrandedQrStickerSurfaceFrame::contentInsetPx();

        return [
            'box_x' => $boxX,
            'box_y' => $boxY,
            'box_width' => $boxWidth,
            'box_height' => $boxHeight,
            'logo_x' => $boxX + $inset,
            'logo_y' => $boxY + $inset,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function boxOrigin(QrStickerTenantLogoPlacement $placement, int $boxWidth, int $boxHeight): array
    {
        return match ($placement) {
            QrStickerTenantLogoPlacement::TopLeft => [
                Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_LEFT_PX,
                Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_TOP_PX,
            ],
            QrStickerTenantLogoPlacement::TopRight => [
                Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX
                    - Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_RIGHT_PX
                    - $boxWidth,
                Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_TOP_PX,
            ],
            QrStickerTenantLogoPlacement::BottomLeft => [
                Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_LEFT_PX,
                Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX
                    - Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_BOTTOM_PX
                    - $boxHeight,
            ],
            QrStickerTenantLogoPlacement::BottomRight => [
                Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX
                    - Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_RIGHT_PX
                    - $boxWidth,
                Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX
                    - Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_BOTTOM_PX
                    - $boxHeight,
            ],
            QrStickerTenantLogoPlacement::None => [0, 0],
        };
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function fitSize(int $width, int $height): array
    {
        $maxW = Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_WIDTH_PX
            - BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $maxH = Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_HEIGHT_PX
            - BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        $scale = min($maxW / max(1, $width), $maxH / max(1, $height));

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }

    private static function resizeLogoPreservingAlphaGd(GdImage $logo, int $targetW, int $targetH): GdImage|false
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
