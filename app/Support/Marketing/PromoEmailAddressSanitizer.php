<?php

declare(strict_types=1);

namespace App\Support\Marketing;

/**
 * Scraped mailto/href leftovers: leading //, %20, nested %25 encoding.
 */
final class PromoEmailAddressSanitizer
{
    public static function sanitize(string $email): ?string
    {
        $value = self::stripJunkPrefix(trim($email));
        if ($value === '') {
            return null;
        }

        for ($i = 0; $i < 8; $i++) {
            if (! str_contains($value, '%')) {
                break;
            }

            $decoded = rawurldecode($value);
            if ($decoded === $value) {
                break;
            }

            $value = self::stripJunkPrefix(trim($decoded));
        }

        if (
            $value === ''
            || str_contains($value, '%')
            || str_contains($value, '/')
            || str_contains($value, ' ')
            || str_contains($value, ',')
            || filter_var($value, FILTER_VALIDATE_EMAIL) === false
        ) {
            return null;
        }

        return strtolower($value);
    }

    private static function stripJunkPrefix(string $value): string
    {
        return ltrim($value, "/ \t");
    }
}
