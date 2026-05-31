<?php

namespace App\Actions\Billing;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Corrigeert billing_active_until wanneer een maandplan per ongeluk met een jaarfperiode is geactiveerd.
 */
class RealignSubscriptionPeriodAction
{
    public function handle(Tenant $tenant): Tenant
    {
        if (! $tenant->needsBillingPeriodRealignment()) {
            return $tenant;
        }

        $tenant->forceFill([
            'billing_active_until' => Carbon::now()->addDays($tenant->subscriptionPeriodDays()),
        ])->save();

        return $tenant->fresh();
    }
}
