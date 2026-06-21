<?php

namespace App\Support\Translation;

use Illuminate\Support\Facades\Cache;

final class TranslationSyncCancellation
{
    public const CACHE_KEY = 'translation-sync-cancel';

    public static function request(): void
    {
        Cache::put(
            self::CACHE_KEY,
            true,
            (int) config('translation_sync.timeout_seconds', 7200),
        );
    }

    public static function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function requested(): bool
    {
        return (bool) Cache::get(self::CACHE_KEY, false);
    }
}
