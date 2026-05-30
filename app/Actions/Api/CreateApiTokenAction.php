<?php

namespace App\Actions\Api;

use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Laravel\Sanctum\PersonalAccessToken;

class CreateApiTokenAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user, string $name, ?int $actorUserId = null): string
    {
        $plain = $user->createToken($name)->plainTextToken;

        $token = $user->tokens()->orderByDesc('id')->first();
        if ($token instanceof PersonalAccessToken) {
            $this->audit->record(
                userId: $actorUserId ?? $user->id,
                tenantId: (int) $user->tenant_id,
                action: 'api_token.created',
                modelType: PersonalAccessToken::class,
                modelId: (int) $token->id,
                payload: ['id' => $token->id, 'name' => $name],
            );
        }

        return $plain;
    }
}
