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
        return self::targetLocalesForSource($issue->original_language);
    }

    /**
     * @return list<string>
     */
    public static function targetLocalesForSource(?string $sourceLocale): array
    {
        $source = self::normalize($sourceLocale);

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
            'es' => 'Spanish',
            'it' => 'Italian',
            default => $locale,
        };
    }

    /**
     * BCP 47-taal voor native date-inputs (dd/mm/jjjj in EU, kalender in app-taal).
     */
    public static function dateInputLang(?string $locale = null): string
    {
        return match (self::normalize($locale ?? app()->getLocale())) {
            'nl' => 'nl-NL',
            'en' => 'en-GB',
            'fr' => 'fr-FR',
            'de' => 'de-DE',
            'es' => 'es-ES',
            'it' => 'it-IT',
            default => 'nl-NL',
        };
    }
}
