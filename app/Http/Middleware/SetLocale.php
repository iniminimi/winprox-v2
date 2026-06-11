<?php

namespace App\Http\Middleware;

use App\Support\ResolveAppLocale;
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
        ResolveAppLocale::apply($request);

        return $next($request);
    }
}
