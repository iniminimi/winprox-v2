<?php

declare(strict_types=1);

namespace App\Support\Marketing;

final class PromoLandingUrl
{
    public static function anonymous(): string
    {
        return route('promo', absolute: true);
    }

    public static function forRecipientToken(string $token, ?string $locale = null): string
    {
        $params = ['ref' => $token];
        $normalizedLocale = self::normalizeLocale($locale);
        if ($normalizedLocale !== null) {
            $params['lang'] = $normalizedLocale;
        }

        return route('promo', $params, absolute: true);
    }

    public static function forRecipientTokenOnBaseUrl(string $token, string $baseUrl, ?string $locale = null): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $query = 'ref='.rawurlencode($token);
        $normalizedLocale = self::normalizeLocale($locale);
        if ($normalizedLocale !== null) {
            $query .= '&lang='.rawurlencode($normalizedLocale);
        }

        return $baseUrl.'/promo?'.$query;
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
