<?php

namespace App\Support;

use App\Actions\Contact\IssueEmailUnsubscribeLinkAction;

class EmailUnsubscribeLink
{
    public static function url(string $email): string
    {
        return app(IssueEmailUnsubscribeLinkAction::class)->handle($email);
    }
}
