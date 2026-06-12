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
        if (! in_array($locale, config('manual_capture.locales', []), true)) {
            return null;
        }

        $relative = 'images/manual/'.$locale.'/'.self::filenameForChapter($chapterKey);

        if (! is_file(public_path($relative))) {
            return null;
        }

        return asset($relative);
    }
}
