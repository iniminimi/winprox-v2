<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Enums\PromoLanding;

final class SectorLandingVideo
{
    public static function relativePath(PromoLanding $landing, string $locale): ?string
    {
        $slug = $landing->value;
        $locale = strtolower(trim($locale));
        if ($locale === '') {
            return null;
        }

        $upper = strtoupper($locale);
        $candidates = [
            "video/{$locale}/{$slug}_promo_{$locale}.mp4",
            "video/{$locale}/{$slug}_promo_{$upper}.mp4",
            "video/{$locale}/{$slug}_long_{$upper}.mp4",
            "video/{$locale}/{$slug}_{$upper}.mp4",
            "video/{$locale}/{$slug}_long_{$locale}.mp4",
            "video/{$locale}/{$slug}_{$locale}.mp4",
        ];

        foreach ($candidates as $relative) {
            if (is_file(public_path($relative))) {
                return $relative;
            }
        }

        return null;
    }
}
