<?php

declare(strict_types=1);

namespace App\Support\Sense;

/**
 * Statuswoorden op het D1S-scherm. De firmware kiest het woord bij de meting.
 */
final class SenseScreenWords
{
    /** @var list<string> */
    public const KEYS = ['cold', 'comfort', 'warm', 'dry', 'optimal', 'humid', 'good', 'high', 'pure'];

    /**
     * @return array<string, string>
     */
    public static function forLocale(string $locale): array
    {
        $locale = in_array($locale, config('locales.supported'), true) ? $locale : (string) config('locales.default', 'nl');
        $words = [];
        foreach (self::KEYS as $key) {
            $words[$key] = (string) trans('screen.sense.words.'.$key, [], $locale);
        }

        return $words;
    }
}
