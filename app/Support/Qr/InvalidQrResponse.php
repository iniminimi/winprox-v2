<?php

declare(strict_types=1);

namespace App\Support\Qr;

use Illuminate\Http\Response;

/**
 * Publieke foutkaart voor ongeldige, beschadigde, inactieve of vervallen QR-scans.
 */
final class InvalidQrResponse
{
    public static function make(): Response
    {
        return response()->view('qr.invalid', [], 404);
    }

    public static function abort(): never
    {
        abort(self::make());
    }
}
