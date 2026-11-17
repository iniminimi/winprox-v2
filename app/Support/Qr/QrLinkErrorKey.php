<?php

namespace App\Support\Qr;

use InvalidArgumentException;

/** Vertaalsleutel voor een foutcode van LinkQrCodeToUnitAction (nooit de ruwe melding tonen). */
class QrLinkErrorKey
{
    public static function for(InvalidArgumentException $e): string
    {
        return match ($e->getMessage()) {
            'qr_link_tenant_mismatch' => 'qr.connect.errors.tenant_mismatch',
            'qr_link_not_linkable' => 'qr.connect.errors.not_linkable',
            'qr_link_already_linked' => 'qr.connect.errors.already_linked',
            default => 'qr.connect.errors.failed',
        };
    }
}
