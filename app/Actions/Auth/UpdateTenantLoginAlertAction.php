<?php

namespace App\Actions\Auth;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

/**
 * Zet het uurvenster voor het aanmeldalarm. Standaard uit.
 */
class UpdateTenantLoginAlertAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, bool $enabled, string $start, string $end, int $actorUserId): void
    {
        $tenant->update([
            'login_alert_enabled' => $enabled,
            'login_alert_start' => $start,
            'login_alert_end' => $end,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'auth.login_alert_setting',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: [
                'enabled' => $enabled,
                'start' => $start,
                'end' => $end,
            ],
        );
    }
}
