<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use Illuminate\Http\Request;

/**
 * HTTP-sessie: voorkom dubbele anonieme promo-bezoeken binnen één browsersessie.
 */
final class PromoVisitSession
{
    private const ANONYMOUS_LOGGED_KEY = 'promo_anonymous_visit_logged';

    public static function shouldLogAnonymousVisit(Request $request): bool
    {
        if ($request->session()->get(self::ANONYMOUS_LOGGED_KEY) === true) {
            return false;
        }

        $request->session()->put(self::ANONYMOUS_LOGGED_KEY, true);

        return true;
    }
}
