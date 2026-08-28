<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Enums\QrPrintablePageBackgroundPreset;
use App\Models\TenantQrStickerSheetSetting;
use RuntimeException;

/**
 * Full-page background for A6/A5/A4 printable QR Word exports.
 * Resolve order: tenant upload → preset from shared Settings row → first stock default.
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
        $normalized = QrPrintablePageBackgroundPreset::normalizePresetKey($presetKey);
        $stockPath = QrPrintablePageStockBackgroundCatalog::absolutePathForPresetKey($normalized);
        if ($stockPath !== null) {
            return $stockPath;
        }

        return QrPrintablePageStockBackgroundCatalog::absolutePathForPresetKey(
            QrPrintablePageBackgroundPreset::defaultPresetKey(),
        ) ?? throw new RuntimeException('Printable QR page stock background catalog is empty.');
    }

    public static function defaultAbsolutePath(): string
    {
        return self::absolutePathForPresetKey(QrPrintablePageBackgroundPreset::defaultPresetKey());
    }
}
