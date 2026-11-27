<?php

namespace App\Support\Auth;

use Carbon\CarbonInterface;

/**
 * Venster voor het aanmeldalarm. 22:00–06:00 loopt over middernacht.
 */
final class LoginAlertWindow
{
    public static function contains(CarbonInterface $moment, string $start, string $end): bool
    {
        $time = $moment->timezone('Europe/Brussels')->format('H:i');

        if ($start === $end) {
            return false;
        }

        if ($start < $end) {
            return $time >= $start && $time < $end;
        }

        return $time >= $start || $time < $end;
    }
}
