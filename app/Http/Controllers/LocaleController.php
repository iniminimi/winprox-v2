<?php

namespace App\Http\Controllers;

use App\Actions\Users\SetUserLocaleAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Bewaart de talenkeuze in de sessie (en op de gebruiker indien ingelogd), daarna terug.
     * Whitelist via config/locales.php; onbekende locales worden genegeerd.
     */
    public function __invoke(Request $request, string $locale, SetUserLocaleAction $setUserLocale): RedirectResponse
    {
        if (in_array($locale, config('locales.supported', []), true)) {
            $request->session()->put('locale', $locale);

            $user = $request->user();
            if ($user !== null) {
                $setUserLocale->handle($user, $locale);
            }
        }

        return back();
    }
}
