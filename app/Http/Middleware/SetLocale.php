<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zet de app-locale op basis van de sessie-keuze (gemaakt via /locale/{locale}).
 * Valt terug op de standaardlocale uit config/locales.php.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('locales.supported', []);
        $default = config('locales.default', config('app.locale'));

        $sessionLocale = $request->session()->get('locale');
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

        app()->setLocale($locale);

        return $next($request);
    }
}
