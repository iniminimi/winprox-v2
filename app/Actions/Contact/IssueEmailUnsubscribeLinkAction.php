<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Models\EmailActionToken;
use App\Models\EmailUnsubscribe;
use Illuminate\Support\Facades\URL;

class IssueEmailUnsubscribeLinkAction
{
    public const PURPOSE = 'unsub';

    public function handle(string $email): string
    {
        $email = EmailUnsubscribe::normalizeEmail($email);

        $row = EmailActionToken::query()
            ->where('email', $email)
            ->where('purpose', self::PURPOSE)
            ->first();

        if ($row === null) {
            do {
                $token = (string) random_int(10000000, 99999999);
            } while (EmailActionToken::query()->where('token', $token)->exists());

            $row = EmailActionToken::query()->create([
                'email' => $email,
                'token' => $token,
                'purpose' => self::PURPOSE,
            ]);
        }

        return URL::route('email.unsubscribe', ['token' => $row->token], true);
    }
}
