<?php

declare(strict_types=1);

namespace App\Support\Marketing;

/**
 * OG-afbeeldingen uit public/images/promo/og_*.jpg (Messenger-vriendelijk JPEG).
 * og_1 = site (welcome, promo, …); og_2 = QR-portaal.
 */
final class PromoOgImage
{
    private const OG_WIDTH = 1200;

    private const OG_HEIGHT = 630;

    private const MIME = 'image/jpeg';

    private const SITE_FILE = 'og_1.jpg';

    private const PORTAL_FILE = 'og_2.jpg';

    /**
     * @return array{url: string, width: int, height: int, type: string}
     */
    public static function forSite(): array
    {
        return self::fromNamedFile(self::SITE_FILE);
    }

    /**
     * @return array{url: string, width: int, height: int, type: string}
     */
    public static function forPortal(): array
    {
        return self::fromNamedFile(self::PORTAL_FILE);
    }

    /**
     * @return array{url: string, width: int, height: int, type: string}
     */
    private static function fromNamedFile(string $filename): array
    {
        $path = public_path('images/promo/'.$filename);

        if (! is_file($path)) {
            $path = public_path('images/promo/'.self::SITE_FILE);
        }

        return self::fromPath($path);
    }

    /**
     * @return array{url: string, width: int, height: int, type: string}
     */
    private static function fromPath(string $path): array
    {
        $size = @getimagesize($path);

        return [
            'url' => asset('images/promo/'.basename($path)),
            'width' => is_array($size) ? (int) $size[0] : self::OG_WIDTH,
            'height' => is_array($size) ? (int) $size[1] : self::OG_HEIGHT,
            'type' => is_array($size) && isset($size['mime']) ? (string) $size['mime'] : self::MIME,
        ];
    }
}
