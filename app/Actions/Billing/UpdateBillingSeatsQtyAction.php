<?php

namespace App\Actions\Billing;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

/**
 * Per-seat plannen (Checkmate): tenant kiest het aantal seats zelf
 * (docs/CHECKMATE.md §7). Zolang Stripe niet live is: direct actief,
 * gefactureerd vanaf volgende periode — geen proratie.
 */
class UpdateBillingSeatsQtyAction
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    public function handle(Tenant $tenant, int $qty, ?int $actorUserId = null): Tenant
    {
        if (! $tenant->planUsesSeatQuantity()) {
            throw new InvalidArgumentException('seats_qty_not_editable');
        }

        if ($qty < 1) {
            throw new InvalidArgumentException('seats_qty_invalid');
        }

        $tenant->assertSeatsQtyNotBelowActive($qty);

        $tenant->forceFill(['billing_seats_qty' => $qty])->save();
        $fresh = $tenant->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->id,
            action: 'tenant.billing_seats_qty_updated',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: [
                'id' => $fresh->id,
                'billing_seats_qty' => $qty,
                'active_seats' => $fresh->currentSeatsCount(),
            ],
        );

        return $fresh;
    }
}
