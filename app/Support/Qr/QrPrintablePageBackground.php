<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Enums\QrPrintablePageBackgroundPreset;
use App\Models\TenantQrStickerSheetSetting;
use RuntimeException;

/**
 * Full-page background for A6/A5/A4 printable QR Word exports.
 * Resolve order: tenant upload → preset from shared Settings row → blue default.
 */
final class QrPrintablePageBackground
{
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

        return self::absolutePathForPresetKey(
            QrPrintablePageBackgroundPreset::presetKeyFromSetting($sheetSettings),
        );
    }

    public static function absolutePathForPresetKey(string $presetKey): string
    {
        $stockPath = QrPrintablePageStockBackgroundCatalog::absolutePathForPresetKey($presetKey);
        if ($stockPath !== null) {
            return $stockPath;
        }

        return QrPrintablePageBackgroundPreset::tryFrom($presetKey)?->absolutePath()
            ?? QrPrintablePageBackgroundPreset::default()->absolutePath();
    }

    public static function defaultAbsolutePath(): string
    {
        return QrPrintablePageBackgroundPreset::default()->absolutePath();
    }
}
