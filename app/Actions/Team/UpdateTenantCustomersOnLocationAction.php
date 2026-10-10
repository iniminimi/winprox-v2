<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class UpdateTenantCustomersOnLocationAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, int $tenantId, bool $enabled, ?int $actorUserId): Tenant
    {
        if ((int) $tenant->id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        // Checkmate forceert klanten-op-locatie; niet uitzetten via Instellingen.
        if ($tenant->checkmateMode() && ! $enabled) {
            throw new InvalidArgumentException('checkmate_requires_customers_on_location');
        }

        $tenant->forceFill([
            'customers_on_location' => $enabled,
        ])->save();

        $fresh = $tenant->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->id,
            action: 'tenant.customers_on_location_updated',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: [
                'customers_on_location' => $enabled,
            ],
        );

        return $fresh;
    }
}
