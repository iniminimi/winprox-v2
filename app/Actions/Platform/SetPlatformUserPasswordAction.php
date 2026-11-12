<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Zet een nieuw wachtwoord voor een organisatiegebruiker, zonder e-mail.
 * De superuser geeft het wachtwoord zelf door.
 */
final class SetPlatformUserPasswordAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user, string $password, int $actorUserId): User
    {
        $user->update([
            'password' => $password,
            'remember_token' => null,
        ]);

        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $user->tenant_id !== null ? (int) $user->tenant_id : null,
            action: 'user.password_set_by_platform',
            modelType: User::class,
            modelId: (int) $user->id,
            payload: ['email' => $user->email],
        );

        return $user->fresh();
    }
}
