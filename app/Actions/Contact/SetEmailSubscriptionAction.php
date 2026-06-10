<?php

namespace App\Actions\Contact;

use App\Models\EmailUnsubscribe;

class SetEmailSubscriptionAction
{
    public function handle(string $email, bool $unsubscribed): void
    {
        $email = EmailUnsubscribe::normalizeEmail($email);

        if ($unsubscribed) {
            $row = EmailUnsubscribe::query()->firstOrNew(['email' => $email]);
            $row->unsubscribed_at = now();
            $row->save();

            return;
        }

        EmailUnsubscribe::query()->where('email', $email)->delete();
    }
}
