<?php

namespace App\Support\Http;

/**
 * Herkent een smartphone aan de User-Agent. Tablets (iPad, Android zonder Mobile) blijven beheer.
 */
final class PhoneUserAgent
{
    public static function isPhone(?string $userAgent): bool
    {
        if ($userAgent === null) {
            return false;
        }

        $userAgent = trim($userAgent);
        if ($userAgent === '') {
            return false;
        }

        if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $userAgent) === 1) {
            return false;
        }

        if (preg_match('/iPhone|iPod/i', $userAgent) === 1) {
            return true;
        }

        if (preg_match('/Android/i', $userAgent) === 1 && preg_match('/Mobile/i', $userAgent) === 1) {
            return true;
        }

        return preg_match('/webOS|BlackBerry|IEMobile|Opera Mini|Opera Mobi|Windows Phone/i', $userAgent) === 1;
    }
}
