<?php

declare(strict_types=1);

namespace App\Support\Marketing;

final class PromoCampaignHtmlSanitizer
{
    /** @var string */
    private const ALLOWED_TAGS = '<p><br><strong><em><ul><ol><li><a>';

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);
        if ($html === '') {
            return null;
        }

        $html = strip_tags($html, self::ALLOWED_TAGS);
        if (self::isBlank($html)) {
            return null;
        }

        return $html;
    }

    public static function forEditor(?string $html): string
    {
        return PromoCampaignQuillHtmlNormalizer::normalize($html);
    }

    public static function isBlank(?string $html): bool
    {
        if ($html === null) {
            return true;
        }

        $text = trim(preg_replace('/\s+/u', '', strip_tags($html)) ?? '');

        return $text === '';
    }
}
