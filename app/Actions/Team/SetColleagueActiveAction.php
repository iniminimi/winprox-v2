<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditRecorder;

/**
 * (De)activeert een collega-gebruiker. Inactieve gebruikers kunnen niet inloggen
 * (afgedwongen in de login-flow).
 */
class SetColleagueActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user, bool $active, ?int $actorUserId = null): User
    {
        if ($active && ! $user->is_active && ! $user->is_superuser) {
            Tenant::query()->findOrFail($user->tenant_id)->assertCanAddSeats(1);
        }

        $user->update(['is_active' => $active]);

        $fresh = $user->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'user.colleague_active_changed',
            modelType: User::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'is_active' => $fresh->is_active],
        );

        return $fresh;
    }
}
