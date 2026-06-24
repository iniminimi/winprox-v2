<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use RuntimeException;

final class PromoBaseUrl
{
    public static function resolve(?string $override = null): string
    {
        $candidate = trim($override ?? (string) config('app.url'));
        $candidate = rtrim($candidate, '/');

        if ($candidate === '') {
            throw new RuntimeException('Promo base URL is required (APP_URL or --promo-base-url).');
        }

        if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException("Invalid promo base URL: {$candidate}");
        }

        return $candidate;
    }

    public static function isLocalhost(string $baseUrl): bool
    {
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
