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

        return strip_tags($html, self::ALLOWED_TAGS);
    }
}
