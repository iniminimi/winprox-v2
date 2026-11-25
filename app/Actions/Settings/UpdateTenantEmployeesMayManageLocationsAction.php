<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

/** Beheerders laten backoffice-medewerkers locaties toevoegen en beheren. */
final class UpdateTenantEmployeesMayManageLocationsAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, bool $enabled, int $actorUserId): Tenant
    {
        $tenant->update(['employees_may_manage_locations' => $enabled]);
        $fresh = $tenant->refresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->id,
            action: 'tenant.employees_may_manage_locations_updated',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: ['employees_may_manage_locations' => $enabled],
        );

        return $fresh;
    }
}
