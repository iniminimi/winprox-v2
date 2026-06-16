<?php

namespace App\Actions\Issues;

use App\Enums\IssueSource;
use App\Mail\NewQrIssueMail;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Stuurt een e-mail naar alle collega-gebruikers (admin/medewerker) die dit hebben ingeschakeld,
 * wanneer een nieuwe melding via het QR-portaal is aangemaakt.
 */
class NotifySubscribedUsersOfNewQrIssueAction
{
    public function handle(Issue $issue): int
    {
        if ($issue->source !== IssueSource::Qr) {
            return 0;
        }

        $issue->loadMissing(['location', 'unit', 'tenant']);

        $queued = 0;

        User::query()
            ->where('tenant_id', $issue->tenant_id)
            ->where('is_active', true)
            ->where('is_superuser', false)
            ->where('notify_on_new_issue_email', true)
            ->whereIn('role', User::ROLES)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($issue, &$queued) {
                foreach ($users as $user) {
                    if (! filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }

                    Mail::to((string) $user->email)->queue(new NewQrIssueMail($user, $issue));
                    $queued++;
                }
            });

        return $queued;
    }
}
