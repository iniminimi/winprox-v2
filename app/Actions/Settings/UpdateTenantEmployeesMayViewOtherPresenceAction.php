<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

/** Beheerders laten medewerkers de aanwezigheid van andere locaties zien. */
final class UpdateTenantEmployeesMayViewOtherPresenceAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, bool $enabled, int $actorUserId): Tenant
    {
        $tenant->update(['employees_may_view_other_presence' => $enabled]);
        $fresh = $tenant->refresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->id,
            action: 'tenant.employees_may_view_other_presence_updated',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: ['employees_may_view_other_presence' => $enabled],
        );

        return $fresh;
    }
}
