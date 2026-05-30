<?php

namespace App\Actions\Api;

use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeApiTokenAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user, int $tokenId, ?int $actorUserId = null): void
    {
        $token = $user->tokens()->whereKey($tokenId)->first();
        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        $id = (int) $token->id;
        $name = $token->name;
        $token->delete();

        $this->audit->record(
            userId: $actorUserId ?? $user->id,
            tenantId: (int) $user->tenant_id,
            action: 'api_token.revoked',
            modelType: PersonalAccessToken::class,
            modelId: $id,
            payload: ['id' => $id, 'name' => $name],
        );
    }
}
