<?php

declare(strict_types=1);

namespace App\Support\Qr;

use GdImage;
use Imagick;

/**
 * Request-scoped cache for sticker export logo decodes (same tenant logo × many labels).
 * Cleared after each Word export batch — see QrStickerWordExporter.
 */
final class QrStickerRasterCache
{
    /** @var array<string, GdImage> */
    private static array $gdSources = [];

    /** @var array<string, Imagick> */
    private static array $imagickSources = [];

    /** Shared GD image — do not imagedestroy; cleared via {@see clear()}. */
    public static function gdSource(string $absolutePath): GdImage|false
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $key = self::cacheKey($absolutePath);
        if (isset(self::$gdSources[$key])) {
            return self::$gdSources[$key];
        }

        $decoded = self::decodeGdFromFile($absolutePath);
        if ($decoded === false) {
            return false;
        }

        self::$gdSources[$key] = $decoded;

        return $decoded;
    }

    public static function imagickSource(string $absolutePath): ?Imagick
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $key = self::cacheKey($absolutePath);
        if (isset(self::$imagickSources[$key])) {
            return self::$imagickSources[$key];
        }

        if (! class_exists(Imagick::class)) {
            return null;
        }

        try {
            $imagick = new Imagick;
            if (str_ends_with(strtolower($absolutePath), '.svg')) {
                $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
            }
            $imagick->readImage($absolutePath);
            self::$imagickSources[$key] = $imagick;

            return $imagick;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function clear(): void
    {
        foreach (self::$gdSources as $image) {
            imagedestroy($image);
        }

        foreach (self::$imagickSources as $image) {
            $image->clear();
        }

        self::$gdSources = [];
        self::$imagickSources = [];
    }

    private static function cacheKey(string $absolutePath): string
    {
        $mtime = @filemtime($absolutePath);

        return $absolutePath.'|'.($mtime !== false ? (string) $mtime : '0');
    }

    private static function decodeGdFromFile(string $absolutePath): GdImage|false
    {
        if (str_ends_with(strtolower($absolutePath), '.svg')) {
            return self::rasterizeSvgWithGd($absolutePath);
        }

        $binary = file_get_contents($absolutePath);
        if ($binary === false || $binary === '') {
            return false;
        }

        return @imagecreatefromstring($binary);
    }

    private static function rasterizeSvgWithGd(string $svgPath): GdImage|false
    {
        $imagick = self::imagickSource($svgPath);
        if ($imagick === null) {
            return false;
        }

        try {
            $clone = clone $imagick;
            $clone->setImageFormat('png');

            return @imagecreatefromstring($clone->getImageBlob());
        } catch (\Throwable) {
            return false;
        }
    }
}
