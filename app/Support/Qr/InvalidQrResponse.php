<?php

declare(strict_types=1);

namespace App\Support\Qr;

use Illuminate\Http\Response;

/**
 * Publieke foutkaart voor ongeldige, beschadigde, inactieve of vervallen QR-scans.
 * $data kan `messageKey` (vertaalsleutel) en `portalHomeUrl` (link naar het
 * portaal op de vaste aanmeldlink) meegeven voor een bruikbare kaart.
 */
final class InvalidQrResponse
{
    public static function make(array $data = []): Response
    {
        return response()->view('qr.invalid', $data, 404);
    }

    public static function abort(array $data = []): never
    {
        abort(self::make($data));
    }
}
