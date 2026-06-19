<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use RuntimeException;

final class PromoRecipientToken
{
    public const PREFIX = 'prm_';

    public static function generate(): string
    {
        for ($i = 0; $i < 64; $i++) {
            $token = self::PREFIX.bin2hex(random_bytes(8));
            if (strlen($token) <= 32) {
                return $token;
            }
        }

        throw new RuntimeException('Could not generate a unique promo recipient token.');
    }

    public static function normalize(string $raw): string
    {
        $raw = strtolower(trim($raw));

        if ($raw === '' || ! preg_match('/^prm_[0-9a-f]{16}$/', $raw)) {
            return '';
        }

        return $raw;
    }
}
