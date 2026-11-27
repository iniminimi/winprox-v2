<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserPasskeyCredential;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

/**
 * Een beheerder trekt de gekoppelde gsm in zodat die persoon een nieuwe kan registreren.
 */
class ReleaseUserPhoneAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $target, int $tenantId, int $actorUserId): void
    {
        if ((int) $target->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $credentials = UserPasskeyCredential::query()->where('user_id', $target->id)->get();
        $labels = $credentials->pluck('device_label')->filter()->implode(', ');

        foreach ($credentials as $credential) {
            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'auth.phone_released',
                modelType: User::class,
                modelId: (int) $target->id,
                payload: [
                    'user_id' => (int) $target->id,
                    'user_name' => $target->name,
                    'device_label' => $credential->device_label,
                ],
            );
            $credential->delete();
        }

        $target->update([
            'phone_reregistration_open' => true,
            'phone_revoked_label' => $labels !== '' ? $labels : null,
        ]);
    }
}
