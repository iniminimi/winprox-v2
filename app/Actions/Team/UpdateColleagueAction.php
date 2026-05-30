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
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? $user->role,
        ]);

        $fresh = $user->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'user.colleague_updated',
            modelType: User::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'email' => $fresh->email, 'role' => $fresh->role],
        );

        return $fresh;
    }
}
