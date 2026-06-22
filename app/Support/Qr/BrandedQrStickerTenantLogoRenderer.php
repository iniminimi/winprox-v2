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
        $frame = self::frameLayout($placement, $targetW, $targetH);

        BrandedQrStickerSurfaceFrame::drawOnGd(
            $canvas,
            $frame['box_x'],
            $frame['box_y'],
            $frame['box_width'],
            $frame['box_height'],
        );

        BrandedQrStickerLogoRaster::compositeOnGd(
            $canvas,
            $logoPath,
            $frame['logo_x'],
            $frame['logo_y'],
            $targetW,
            $targetH,
        );
    }

    public static function drawOnImagick(Imagick $canvas, string $logoPath, QrStickerTenantLogoPlacement $placement): void
    {
        if ($placement === QrStickerTenantLogoPlacement::None || ! is_file($logoPath)) {
            return;
        }

        $logo = QrStickerRasterCache::gdSource($logoPath);
        if ($logo === false) {
            $cachedLogo = QrStickerRasterCache::imagickSource($logoPath);
            if ($cachedLogo === null) {
                return;
            }

            $targetW = max(1, $cachedLogo->getImageWidth());
            $targetH = max(1, $cachedLogo->getImageHeight());
            [$targetW, $targetH] = self::fitSize($targetW, $targetH);
        } else {
            [$targetW, $targetH] = self::fitSize(imagesx($logo), imagesy($logo));
        }

        $frame = self::frameLayout($placement, $targetW, $targetH);

        BrandedQrStickerSurfaceFrame::drawOnImagick(
            $canvas,
            $frame['box_x'],
            $frame['box_y'],
            $frame['box_width'],
            $frame['box_height'],
        );

        BrandedQrStickerLogoRaster::compositeOnImagick(
            $canvas,
            $logoPath,
            $frame['logo_x'],
            $frame['logo_y'],
            $targetW,
            $targetH,
        );
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
}
