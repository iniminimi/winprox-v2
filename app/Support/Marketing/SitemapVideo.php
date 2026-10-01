<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Enums\PromoLanding;
use Illuminate\Support\Facades\Lang;

/**
 * Video-entries voor sitemap.xml (Google Video sitemap-extensie).
 *
 * Per (route, locale) wordt dezelfde resolutie gebruikt als de pagina zelf
 * (`SectorLandingVideo::relativePath`), zodat sitemap en HTML nooit uit elkaar lopen.
 */
final class SitemapVideo
{
    /**
     * @return array<int, array{content_loc: string, thumbnail_loc: string, title: string, description: string, upload_date: string}>
     */
    public static function forRoute(string $routeName, string $locale): array
    {
        if ($routeName === 'welcome') {
            return self::welcomeEntries($locale);
        }

        $landing = PromoLanding::tryFrom($routeName);
        if (! $landing instanceof PromoLanding) {
            return [];
        }

        $relative = SectorLandingVideo::relativePath($landing, $locale);
        if ($relative === null) {
            return [];
        }

        $key = 'landings.'.$landing->value;
        $title = Lang::has("{$key}.video.title", $locale)
            ? (string) Lang::get("{$key}.video.title", [], $locale)
            : (string) Lang::get('landings.shared.video_title', [], $locale);
        $description = Lang::has("{$key}.video.lead", $locale)
            ? (string) Lang::get("{$key}.video.lead", [], $locale)
            : (string) Lang::get("{$key}.social.og_description", [], $locale);

        return [[
            'content_loc' => asset($relative),
            'thumbnail_loc' => asset(self::thumbnailFor($landing)),
            'title' => $title,
            'description' => $description,
            'upload_date' => self::uploadDate($relative),
        ]];
    }

    /**
     * @return array<int, array{content_loc: string, thumbnail_loc: string, title: string, description: string, upload_date: string}>
     */
    private static function welcomeEntries(string $locale): array
    {
        $relative = 'video/welcome.mp4';
        if (! is_file(public_path($relative))) {
            return [];
        }

        return [[
            'content_loc' => asset($relative),
            'thumbnail_loc' => asset('images/promo/og_1.jpg'),
            'title' => (string) Lang::get('welcome.video.title', [], $locale),
            'description' => (string) Lang::get('welcome.social.og_description', [], $locale),
            'upload_date' => self::uploadDate($relative),
        ]];
    }

    private static function uploadDate(string $relative): string
    {
        $mtime = @filemtime(public_path($relative));

        return date('c', is_int($mtime) ? $mtime : time());
    }

    private static function thumbnailFor(PromoLanding $landing): string
    {
        return match ($landing) {
            PromoLanding::Hospitality => 'images/landing/hospitality/image_01.jpg',
            PromoLanding::Industry => 'images/landing/industry/image_01.jpg',
            PromoLanding::Healthcare => 'images/landing/healthcare/image_01.jpg',
            PromoLanding::Government => 'images/landing/gouvernment/image_01.jpg',
            // RealEstate gebruikt de hospitality_long-video als bron.
            PromoLanding::RealEstate => 'images/landing/hospitality/image_01.jpg',
            PromoLanding::WorkOnLocation => 'images/landing/work_on_location/image01.jpg',
            PromoLanding::Checkmate => 'images/promo/og_2.jpg',
            PromoLanding::Prikklok => 'images/landing/LCD.jpg',
        };
    }
}
