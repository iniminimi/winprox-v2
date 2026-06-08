<?php

namespace App\Support;

use App\Models\EmailUnsubscribe;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

class EmailUnsubscribeLink
{
    public static function signedUrl(string $email): string
    {
        $normalized = EmailUnsubscribe::normalizeEmail($email);

        return URL::signedRoute(
            'email.unsubscribe',
            ['t' => Crypt::encryptString($normalized)],
            absolute: true
        );
    }
}
