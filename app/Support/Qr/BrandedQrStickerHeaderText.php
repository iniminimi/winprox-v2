<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\Tenant;

final class BrandedQrStickerHeaderText
{
    public static function resolve(?Tenant $tenant, ?string $headerFallback): ?string
    {
        $tenantText = self::tenantHeaderText($tenant);
        if ($tenantText !== '') {
            return self::fitForSticker($tenantText);
        }

        $fallback = self::normalize((string) ($headerFallback ?? ''));

        return $fallback !== '' ? self::fitForSticker($fallback) : null;
    }

    /** Portal unit line below QR when tenant header text is configured. */
    public static function unitCaption(?Tenant $tenant, ?string $headerFallback): ?string
    {
        if (self::tenantHeaderText($tenant) === '') {
            return null;
        }

        $line = self::portalCaptionLine($headerFallback);
        if ($line === '') {
            return null;
        }

        if (mb_strlen($line) > Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS) {
            return self::fitForSticker($line);
        }

        return $line;
    }

    private static function tenantHeaderText(?Tenant $tenant): string
    {
        return self::normalize((string) ($tenant?->qr_sticker_avery_62x89_header_text ?? ''));
    }

    private static function portalCaptionLine(?string $headerFallback): string
    {
        $fallback = trim((string) ($headerFallback ?? ''));
        if ($fallback === '') {
            return '';
        }

        $lines = preg_split('/\R/u', $fallback) ?: [];
        $line = trim((string) ($lines[0] ?? ''));

        return trim(preg_replace('/\s+/u', ' ', $line) ?? '');
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
