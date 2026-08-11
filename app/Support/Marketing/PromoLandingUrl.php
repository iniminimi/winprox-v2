<?php

declare(strict_types=1);

namespace App\Support\Marketing;

final class PromoLandingUrl
{
    public static function anonymous(?string $locale = null): string
    {
        return route('promo', [
            'locale' => self::normalizeLocale($locale) ?? config('locales.default', 'nl'),
        ], absolute: true);
    }

    public static function forRecipientToken(string $token, ?string $locale = null): string
    {
        return route('promo', [
            'locale' => self::normalizeLocale($locale) ?? config('locales.default', 'nl'),
            'ref' => $token,
        ], absolute: true);
    }

    public static function welcomeForRecipientToken(string $token, ?string $locale = null): string
    {
        return route('welcome', [
            'locale' => self::normalizeLocale($locale) ?? config('locales.default', 'nl'),
            'ref' => $token,
        ], absolute: true);
    }

    public static function forRecipientTokenOnBaseUrl(string $token, string $baseUrl, ?string $locale = null): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $normalizedLocale = self::normalizeLocale($locale) ?? config('locales.default', 'nl');
        $query = 'ref='.rawurlencode($token);

        return $baseUrl.'/'.$normalizedLocale.'/promo?'.$query;
    }

    public static function welcomeForRecipientTokenOnBaseUrl(string $token, string $baseUrl, ?string $locale = null): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $normalizedLocale = self::normalizeLocale($locale) ?? config('locales.default', 'nl');
        $query = 'ref='.rawurlencode($token);

        return $baseUrl.'/'.$normalizedLocale.'/?'.$query;
    }

    private static function normalizeLocale(?string $locale): ?string
    {
        if ($locale === null || trim($locale) === '') {
            return null;
        }

        $locale = strtolower(trim($locale));
        $supported = config('locales.supported', []);

        return in_array($locale, $supported, true) ? $locale : null;
    }
}
