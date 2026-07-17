<?php

namespace App\Http\Middleware;

use App\Support\ResolveAppLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zet de app-locale: route-prefix (marketing) wint, anders sessie/cookie/browser/default.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('locales.supported', []);
        $routeLocale = $request->route('locale');

        if (is_string($routeLocale) && in_array($routeLocale, $supported, true)) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $routeLocale);
            }
            Cookie::queue(ResolveAppLocale::COOKIE_NAME, $routeLocale, ResolveAppLocale::COOKIE_MINUTES);
            app()->setLocale($routeLocale);
            URL::defaults(['locale' => $routeLocale]);

            return $next($request);
        }

        $locale = ResolveAppLocale::apply($request);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
