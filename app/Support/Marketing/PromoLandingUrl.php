<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Enums\PromoLanding;

final class PromoLandingUrl
{
    public static function anonymous(?string $locale = null, ?PromoLanding $landing = null): string
    {
        return self::forLanding($landing ?? PromoLanding::default(), $locale);
    }

    public static function forRecipientToken(string $token, ?string $locale = null, ?PromoLanding $landing = null): string
    {
        return self::forLanding($landing ?? PromoLanding::default(), $locale, $token);
    }

    public static function forLanding(PromoLanding $landing, ?string $locale = null, ?string $token = null): string
    {
        $parameters = [
            'locale' => self::normalizeLocale($locale) ?? config('locales.default', 'nl'),
        ];
        if ($token !== null && $token !== '') {
            $parameters['ref'] = $token;
        }

        return route($landing->routeName(), $parameters, absolute: true);
    }

    public static function welcomeForRecipientToken(string $token, ?string $locale = null): string
    {
        return route('welcome', [
            'locale' => self::normalizeLocale($locale) ?? config('locales.default', 'nl'),
            'ref' => $token,
        ], absolute: true);
    }

    public static function forRecipientTokenOnBaseUrl(
        string $token,
        string $baseUrl,
        ?string $locale = null,
        ?PromoLanding $landing = null,
    ): string {
        $landing ??= PromoLanding::default();
        $baseUrl = rtrim($baseUrl, '/');
        $normalizedLocale = self::normalizeLocale($locale) ?? config('locales.default', 'nl');
        $query = 'ref='.rawurlencode($token);

        return $baseUrl.'/'.$normalizedLocale.'/'.$landing->value.'?'.$query;
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
