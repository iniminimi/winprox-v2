<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Bepaalt de app-locale (sessie → gebruiker → cookie → config/locales.default).
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

    public static function resolve(Request $request): string
    {
        $supported = config('locales.supported', []);
        $default = config('locales.default', config('app.locale'));

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
            $locale = $default;
        }

        if (! in_array($locale, $supported, true)) {
            $locale = $default;
        }

        return $locale;
    }
}
