<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use Illuminate\Http\Request;

/**
 * HTTP-sessie voor promo-bestemmeling (?ref=) op de publieke promo-pagina.
 */
final class PromoRecipientSession
{
    private const SESSION_KEY = 'promo_recipient_token';

    public static function syncFromRequest(Request $request): ?string
    {
        $fromQuery = PromoRecipientToken::normalize((string) $request->query('ref', ''));
        if ($fromQuery !== '') {
            session([self::SESSION_KEY => $fromQuery]);

            return $fromQuery;
        }

        $fromSession = PromoRecipientToken::normalize((string) session(self::SESSION_KEY, ''));

        return $fromSession !== '' ? $fromSession : null;
    }

    public static function token(): ?string
    {
        $token = PromoRecipientToken::normalize((string) session(self::SESSION_KEY, ''));

        return $token !== '' ? $token : null;
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
