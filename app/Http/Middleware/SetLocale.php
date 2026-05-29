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

        $locale = $request->session()->get('locale', $default);

        if (! in_array($locale, $supported, true)) {
            $locale = $default;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
