<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\TenantQrStickerSheetSetting;
use RuntimeException;

/**
 * Full-page background for A6/A5/A4 printable QR Word exports.
 * Default: WinProx blue PNG (rasterized from SVG). Later: tenant upload/preset via sheet settings.
 */
final class QrPrintablePageBackground
{
    /** Preferred raster for Word/GD export (no PHP Imagick required). */
    public const DEFAULT_RELATIVE = 'images/qr/svg/QR_printable_blue.png';

    /** Source SVG — used when Imagick can read it, or as Settings preset later. */
    public const DEFAULT_SVG_RELATIVE = 'images/qr/svg/QR_printable_blue.svg';

    public static function absolutePathForTemplate(
        QrStickerSheetTemplate $template,
        ?TenantQrStickerSheetSetting $sheetSettings = null,
    ): string {
        if (! $template->isPrintablePage()) {
            throw new RuntimeException('Printable page background is not configured for template '.$template->value.'.');
        }

        $tenantPath = $sheetSettings?->backgroundAbsolutePath();
        if ($tenantPath !== null) {
            return $tenantPath;
        }

        return self::defaultAbsolutePath();
    }

    public static function defaultAbsolutePath(): string
    {
        $png = public_path(self::DEFAULT_RELATIVE);
        if (is_file($png)) {
            return $png;
        }

        $svg = public_path(self::DEFAULT_SVG_RELATIVE);
        if (is_file($svg)) {
            return $svg;
        }

        throw new RuntimeException('Printable QR page background is missing at '.self::DEFAULT_RELATIVE.'.');
    }
}
