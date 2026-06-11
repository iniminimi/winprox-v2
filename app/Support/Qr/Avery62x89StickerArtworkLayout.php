<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Enums\QrStickerTenantLogoPlacement;

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

    /** Upper band — tenant / portal label left-aligned, clears Prox logo (top-right). */
    public const HEADER_PADDING_LEFT_PX = 36;

    /** Clears Prox icon top-right; header may extend close to the logo wordmark. */
    public const HEADER_PADDING_RIGHT_PX = 88;

    public const HEADER_TOP_PX = 72;

    public const HEADER_MAX_HEIGHT_PX = 168;

    public const HEADER_MAX_FONT_SIZE_PX = 36;

    public const HEADER_MIN_FONT_SIZE_PX = 20;

    public const HEADER_LINE_HEIGHT_RATIO = 1.12;

    public const HEADER_MAX_LINES = 3;

    /** Matches settings validation — ~3 lines on the widened header band. */
    public const HEADER_TEXT_MAX_CHARS = 120;

    /** Directly below QR — locatie · unit when tenant header text is set. */
    public const UNIT_CAPTION_TOP_PX = 732;

    public const UNIT_CAPTION_MAX_FONT_SIZE_PX = 22;

    public const UNIT_CAPTION_MIN_FONT_SIZE_PX = 16;

    /** Sticker number (Winprox-YYMM-#####), just under unit caption. */
    public const FOOTER_PADDING_SIDE_PX = 48;

    public const FOOTER_TOP_PX = 756;

    public const FOOTER_MAX_FONT_SIZE_PX = 24;

    public const FOOTER_MIN_FONT_SIZE_PX = 15;

    /** Bottom black band — tenant address block (left) and logo (right). */
    public const TENANT_DETAILS_PADDING_LEFT_PX = 36;

    public const TENANT_DETAILS_PADDING_BOTTOM_PX = 58;

    public const TENANT_DETAILS_LOGO_GAP_PX = 16;

    public const TENANT_DETAILS_MAX_FONT_SIZE_PX = 17;

    public const TENANT_DETAILS_MIN_FONT_SIZE_PX = 12;

    public const TENANT_DETAILS_LINE_HEIGHT_RATIO = 1.12;

    public const TENANT_DETAILS_MAX_LINES = 3;

    public const TENANT_LOGO_MAX_WIDTH_PX = 88;

    public const TENANT_LOGO_MAX_HEIGHT_PX = 64;

    public const TENANT_LOGO_PADDING_RIGHT_PX = 36;

    public const TENANT_LOGO_PADDING_LEFT_PX = 36;

    public const TENANT_LOGO_PADDING_TOP_PX = 72;

    public const TENANT_LOGO_PADDING_BOTTOM_PX = 58;

    public static function headerMaxWidthPx(): int
    {
        return self::CANVAS_WIDTH_PX - self::HEADER_PADDING_LEFT_PX - self::HEADER_PADDING_RIGHT_PX;
    }

    public static function footerMaxWidthPx(): int
    {
        return self::CANVAS_WIDTH_PX - (self::FOOTER_PADDING_SIDE_PX * 2);
    }

    public static function tenantDetailsMaxWidthPx(QrStickerTenantLogoPlacement $logoPlacement): int
    {
        if ($logoPlacement === QrStickerTenantLogoPlacement::BottomRight) {
            return self::CANVAS_WIDTH_PX
                - self::TENANT_DETAILS_PADDING_LEFT_PX
                - self::TENANT_LOGO_MAX_WIDTH_PX
                - self::TENANT_LOGO_PADDING_RIGHT_PX
                - self::TENANT_DETAILS_LOGO_GAP_PX;
        }

        if ($logoPlacement === QrStickerTenantLogoPlacement::BottomLeft) {
            return (int) round(self::CANVAS_WIDTH_PX * 0.55);
        }

        return (int) round(self::CANVAS_WIDTH_PX * 0.62);
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
