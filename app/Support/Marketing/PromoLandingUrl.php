<?php

declare(strict_types=1);

namespace App\Support\Marketing;

final class PromoLandingUrl
{
    public static function anonymous(): string
    {
        return route('promo', absolute: true);
    }

    public static function forRecipientToken(string $token): string
    {
        return route('promo', ['ref' => $token], absolute: true);
    }

    public static function forRecipientTokenOnBaseUrl(string $token, string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        return $baseUrl.'/promo?ref='.rawurlencode($token);
    }
}
