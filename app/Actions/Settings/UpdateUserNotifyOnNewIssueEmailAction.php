<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

final class UpdateUserNotifyOnNewIssueEmailAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user, bool $enabled, int $actorUserId): User
    {
        if ($actorUserId !== (int) $user->id) {
            throw new InvalidArgumentException('Notification preference can only be updated for the authenticated user.');
        }

        $user->update(['notify_on_new_issue_email' => $enabled]);

        $fresh = $user->refresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'user.notify_on_new_issue_email_updated',
            modelType: User::class,
            modelId: (int) $fresh->id,
            payload: ['notify_on_new_issue_email' => $enabled],
        );

        return $fresh;
    }
}
