<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\Tenant;

final class BrandedQrStickerHeaderText
{
    public static function resolve(?Tenant $tenant, ?string $headerFallback): ?string
    {
        $tenantText = trim((string) ($tenant?->qr_sticker_avery_62x89_header_text ?? ''));
        if ($tenantText !== '') {
            return $tenantText;
        }

        $fallback = trim((string) ($headerFallback ?? ''));

        return $fallback !== '' ? $fallback : null;
    }
}
