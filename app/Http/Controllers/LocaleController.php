<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Bewaart de talenkeuze in de sessie en stuurt terug naar de vorige pagina.
     * Whitelist via config/locales.php; onbekende locales worden genegeerd.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, config('locales.supported', []), true)) {
            $request->session()->put('locale', $locale);
        }

        return back();
    }
}
