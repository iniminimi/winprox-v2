<?php

declare(strict_types=1);

namespace App\Support\Marketing;

/**
 * Willekeurige OG-afbeelding uit public/images/promo/og_*.jpg (Messenger-vriendelijk JPEG).
 */
final class PromoOgImage
{
    private const OG_WIDTH = 1200;

    private const OG_HEIGHT = 630;

    private const MIME = 'image/jpeg';

    /**
     * @return array{url: string, width: int, height: int, type: string}
     */
    public static function random(): array
    {
        $files = self::discover();

        if ($files === []) {
            return self::fromPath(public_path('images/promo/og_1.jpg'));
        }

        $path = $files[random_int(0, count($files) - 1)];

        return self::fromPath($path);
    }

    /**
     * @return list<string>
     */
    private static function discover(): array
    {
        $paths = glob(public_path('images/promo/og_*.jpg')) ?: [];

        $files = array_values(array_filter(
            $paths,
            static fn (string $path): bool => is_file($path) && is_readable($path),
        ));

        sort($files);

        return $files;
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
