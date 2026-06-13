<?php

declare(strict_types=1);

namespace App\Support\Manual;

/**
 * Statische handleiding-screenshots in public/images/manual/{locale}/.
 */
final class ManualScreenshotAssets
{
    public static function filenameForChapter(string $chapterKey): string
    {
        return str_replace('.', '-', $chapterKey).'.png';
    }

    public static function publicUrl(string $chapterKey, string $locale): ?string
    {
        return self::publicUrlForFilename(self::filenameForChapter($chapterKey), $locale);
    }

    public static function publicUrlForCaptureId(string $captureId, string $locale): ?string
    {
        return self::publicUrlForFilename($captureId.'.png', $locale);
    }

    private static function publicUrlForFilename(string $filename, string $locale): ?string
    {
        if (! in_array($locale, config('manual_capture.locales', []), true)) {
            return null;
        }

        $relative = 'images/manual/'.$locale.'/'.$filename;

        if (! is_file(public_path($relative))) {
            return null;
        }

        return asset($relative);
    }

    public static function isPortalChapter(string $chapterKey): bool
    {
        return str_starts_with($chapterKey, 'portal.');
    }

    /**
     * @param  array<string, mixed>  $chapter
     * @return array<string, mixed>
     */
    public static function enrichChapter(array $chapter, string $locale): array
    {
        $key = (string) ($chapter['key'] ?? '');

        return [
            ...$chapter,
            'manualScreenshotUrl' => self::publicUrl($key, $locale),
            'manualScreenshotPortal' => self::isPortalChapter($key),
        ];
    }
}
