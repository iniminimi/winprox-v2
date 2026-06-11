<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\Tenant;

final class BrandedQrStickerHeaderText
{
    public static function resolve(?Tenant $tenant, ?string $headerFallback): ?string
    {
        $tenantText = self::normalize((string) ($tenant?->qr_sticker_avery_62x89_header_text ?? ''));
        if ($tenantText !== '') {
            return self::fitForSticker($tenantText);
        }

        $fallback = self::normalize((string) ($headerFallback ?? ''));

        return $fallback !== '' ? self::fitForSticker($fallback) : null;
    }

    public static function fitForSticker(string $text): string
    {
        $text = self::normalize($text);
        $max = Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS;
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    private static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
