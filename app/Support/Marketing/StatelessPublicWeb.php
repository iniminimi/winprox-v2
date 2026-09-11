<?php

declare(strict_types=1);

namespace App\Support\Marketing;

/**
 * Web-middleware die sessie- en CSRF-cookies zet. Publieke fiches/legal/llms
 * laten die weg zodat AI-fetchers (Perplexity fetch_url e.d.) geen login-achtige
 * Set-Cookie zien en LiteSpeed de antwoorden kan cachen.
 *
 * @return list<class-string>
 */
final class StatelessPublicWeb
{
    public static function sessionCookieMiddleware(): array
    {
        return [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ];
    }
}
