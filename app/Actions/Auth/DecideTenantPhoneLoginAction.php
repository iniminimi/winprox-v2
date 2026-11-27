<?php

namespace App\Actions\Auth;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

/**
 * De organisatie kiest of aanmelden met de telefoon aanstaat. Nee blijft onthouden.
 */
class DecideTenantPhoneLoginAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, bool $enabled, int $actorUserId): void
    {
        $tenant->update([
            'backoffice_phone_login_enabled' => $enabled,
            'backoffice_phone_login_decided_at' => now(),
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'auth.phone_login_setting',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: ['enabled' => $enabled],
        );
    }
}
