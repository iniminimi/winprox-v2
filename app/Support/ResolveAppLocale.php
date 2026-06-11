<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Bepaalt de app-locale (sessie → gebruiker → config/locales.default).
 * Gedeeld door SetLocale-middleware en de exception handler (foutpagina's).
 */
final class ResolveAppLocale
{
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

        if (is_string($sessionLocale) && in_array($sessionLocale, $supported, true)) {
            $locale = $sessionLocale;
        } elseif (is_string($userLocale) && in_array($userLocale, $supported, true)) {
            $locale = $userLocale;
        } else {
            $locale = $default;
        }

        if (! in_array($locale, $supported, true)) {
            $locale = $default;
        }

        return $locale;
    }
}
