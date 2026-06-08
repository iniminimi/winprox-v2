<?php

namespace App\Support;

use App\Models\EmailUnsubscribe;

final class EmailUnsubscribeExemptions
{
    public static function isExempt(string $email): bool
    {
        $normalized = EmailUnsubscribe::normalizeEmail($email);

        return in_array($normalized, config('winprox.email_unsubscribe_exempt_recipients', []), true);
    }
}
