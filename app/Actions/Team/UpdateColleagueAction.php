<?php

namespace App\Actions\Team;

use App\Models\User;
use App\Support\Audit\AuditRecorder;

class UpdateColleagueAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, ?int $actorUserId = null): User
    {
        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'locale' => $data['locale'],
            'role' => $data['role'] ?? $user->role,
        ];

        if (array_key_exists('notify_on_new_issue_email', $data)) {
            $attributes['notify_on_new_issue_email'] = (bool) $data['notify_on_new_issue_email'];
        }

        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        $user->update($attributes);

        $fresh = $user->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'user.colleague_updated',
            modelType: User::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'email' => $fresh->email, 'role' => $fresh->role, 'notify_on_new_issue_email' => $fresh->notify_on_new_issue_email],
        );

        return $fresh;
    }
}
