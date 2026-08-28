<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\TenantQrStickerSheetSetting;

final class BrandedQrStickerHeaderText
{
    public static function resolve(?TenantQrStickerSheetSetting $sheetSettings, ?string $headerFallback): ?string
    {
        $tenantText = self::tenantHeaderText($sheetSettings);
        if ($tenantText !== '') {
            return self::fitForSticker($tenantText);
        }

        $fallback = self::normalize((string) ($headerFallback ?? ''));

        return $fallback !== '' ? self::fitForSticker($fallback) : null;
    }

    /** Portal unit line below QR when tenant header text is configured. */
    public static function unitCaption(?TenantQrStickerSheetSetting $sheetSettings, ?string $headerFallback): ?string
    {
        if (self::tenantHeaderText($sheetSettings) === '') {
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

    /**
     * Headline + caption above the QR on printable A6/A5/A4 pages (aligned with sticker header rules).
     *
     * @return array{headline: string, secondary: string}
     */
    public static function printableHeadlineAndCaption(
        ?TenantQrStickerSheetSetting $sheetSettings,
        QrStickerEntry $entry,
    ): array {
        $headerFallback = $entry->headerFallback ?? $entry->locationUnitLabel;
        $primaryText = trim((string) ($entry->stickerNumber ?? $entry->unitLabel));

        if (self::tenantHeaderText($sheetSettings) !== '') {
            $headline = self::resolve($sheetSettings, is_string($headerFallback) ? $headerFallback : null) ?? '';
            $secondary = self::unitCaption($sheetSettings, is_string($headerFallback) ? $headerFallback : null)
                ?? trim((string) ($entry->locationUnitLabel ?? ''));
        } else {
            $headline = trim((string) ($entry->pageHeadline ?? ''));
            if ($headline === '') {
                $headline = self::resolve($sheetSettings, is_string($headerFallback) ? $headerFallback : null) ?? '';
            }
            $secondary = trim((string) ($entry->locationUnitLabel ?? ''));
        }

        if ($secondary !== '' && strcasecmp($secondary, $primaryText) === 0) {
            $secondary = '';
        }
        if ($headline !== '' && strcasecmp($headline, $secondary) === 0) {
            $headline = '';
        }

        return [
            'headline' => $headline,
            'secondary' => $secondary,
        ];
    }

    private static function tenantHeaderText(?TenantQrStickerSheetSetting $sheetSettings): string
    {
        return self::normalize((string) ($sheetSettings?->header_text ?? ''));
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
