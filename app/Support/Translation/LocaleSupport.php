<?php

namespace App\Support\Translation;

use App\Models\Issue;

final class LocaleSupport
{
    public static function normalize(?string $locale): string
    {
        $supported = config('locales.supported', []);
        $default = (string) config('locales.default', 'nl');

        if (is_string($locale) && in_array($locale, $supported, true)) {
            return $locale;
        }

        return $default;
    }

    /**
     * @return list<string>
     */
    public static function targetLocalesFor(Issue $issue): array
    {
        $source = self::normalize($issue->original_language);

        return array_values(array_filter(
            config('locales.supported', []),
            fn (string $locale) => $locale !== $source,
        ));
    }

    public static function languageLabel(string $locale): string
    {
        return match ($locale) {
            'nl' => 'Dutch',
            'en' => 'English',
            'fr' => 'French',
            'de' => 'German',
            default => $locale,
        };
    }
}
