<?php

namespace App\Support;

use App\Support\Marketing\MarketingDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Bepaalt de app-locale (host → sessie → gebruiker → cookie → browsertaal).
 * Een live marktdomein beperkt de talen vóór de rest van de keten.
 * Gedeeld door SetLocale-middleware, exception handler en publieke portalen.
 */
final class ResolveAppLocale
{
    public const COOKIE_NAME = 'locale';

    public const COOKIE_MINUTES = 60 * 24 * 365;

    public static function apply(Request $request): string
    {
        $locale = self::resolve($request);
        app()->setLocale($locale);

        return $locale;
    }

    /**
     * Talen op het uitvoerdersportaal. Mag ruimer zijn dan de backoffice.
     *
     * @return list<string>
     */
    public static function portalLocales(): array
    {
        $portal = config('locales.portal');

        if (! is_array($portal) || $portal === []) {
            $portal = config('locales.supported', []);
        }

        return array_values(array_filter($portal, 'is_string'));
    }

    /**
     * Portaal: ?lang= wint, anders sessie, cookie of browsertaal binnen de portaal-lijst.
     */
    public static function applyPortal(Request $request): string
    {
        $allowed = self::portalLocales();
        $lang = $request->query('lang');

        if (is_string($lang) && in_array($lang, $allowed, true)) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $lang);
            }
            Cookie::queue(self::COOKIE_NAME, $lang, self::COOKIE_MINUTES);

            return $lang;
        }

        return self::resolve($request, $allowed);
    }

    /**
     * @param  list<string>|null  $allowed
     */
    public static function resolve(Request $request, ?array $allowed = null): string
    {
        $supported = $allowed ?? MarketingDomain::localesFor($request);
        $default = $allowed !== null
            ? (string) config('locales.default', 'nl')
            : MarketingDomain::defaultLocaleFor($request);

        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;
        $userLocale = $request->user()?->locale;
        $cookieLocale = $request->cookie(self::COOKIE_NAME)
            ?? $request->cookies->get(self::COOKIE_NAME);

        if (is_string($sessionLocale) && in_array($sessionLocale, $supported, true)) {
            $locale = $sessionLocale;
        } elseif (is_string($userLocale) && in_array($userLocale, $supported, true)) {
            $locale = $userLocale;
        } elseif (is_string($cookieLocale) && in_array($cookieLocale, $supported, true)) {
            $locale = $cookieLocale;
        } else {
            $browserLocale = $request->getPreferredLanguage($supported);
            if (is_string($browserLocale) && in_array($browserLocale, $supported, true)) {
                $locale = $browserLocale;
            } else {
                $locale = $default;
            }
        }

        if (! in_array($locale, $supported, true)) {
            $locale = $default;
        }

        return $locale;
    }
}
