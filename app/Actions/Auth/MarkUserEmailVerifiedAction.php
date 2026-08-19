<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Auth\Events\Verified;

class MarkUserEmailVerifiedAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user): User
    {
        if ($user->hasVerifiedEmail()) {
            return $user;
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Verified($user));

        $this->audit->record(
            userId: (int) $user->id,
            tenantId: $user->tenant_id !== null ? (int) $user->tenant_id : null,
            action: 'auth.email_verified',
            modelType: User::class,
            modelId: (int) $user->id,
            payload: ['email' => (string) $user->email],
        );

        return $user->refresh();
    }
}
