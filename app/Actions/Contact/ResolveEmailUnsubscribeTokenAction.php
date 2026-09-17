<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Models\EmailActionToken;
use App\Models\EmailUnsubscribe;

class ResolveEmailUnsubscribeTokenAction
{
    public function handle(string $token): ?string
    {
        $normalized = trim($token);
        if ($normalized === '' || ! preg_match('/^[0-9]{8}$/', $normalized)) {
            return null;
        }

        $row = EmailActionToken::query()
            ->where('token', $normalized)
            ->where('purpose', IssueEmailUnsubscribeLinkAction::PURPOSE)
            ->first();

        if ($row === null) {
            return null;
        }

        return EmailUnsubscribe::normalizeEmail($row->email);
    }
}
