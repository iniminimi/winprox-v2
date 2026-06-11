<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\TenantQrStickerSheetSetting;
use RuntimeException;

/**
 * Sticker artwork background for branded QR sheet exports.
 * Phase 1: fixed WinProx design. Later: tenant upload with fallback.
 */
final class QrStickerBackground
{
    public const DEFAULT_AVERY_62X89_RELATIVE = 'images/qr/svg/qr_background.png';

    public static function absolutePathForTemplate(
        QrStickerSheetTemplate $template,
        ?TenantQrStickerSheetSetting $sheetSettings = null,
    ): string {
        $tenantPath = $sheetSettings?->backgroundAbsolutePath();
        if ($tenantPath !== null) {
            return $tenantPath;
        }

        return match ($template) {
            QrStickerSheetTemplate::Avery62x89R => self::defaultAvery62x89AbsolutePath(),
            default => throw new RuntimeException('Sticker background is not configured for template '.$template->value.'.'),
        };
    }

    public static function defaultAvery62x89AbsolutePath(): string
    {
        $path = public_path(self::DEFAULT_AVERY_62X89_RELATIVE);
        if (! is_file($path)) {
            throw new RuntimeException('Branded QR sticker background is missing at '.self::DEFAULT_AVERY_62X89_RELATIVE.'.');
        }

        return $path;
    }
}
