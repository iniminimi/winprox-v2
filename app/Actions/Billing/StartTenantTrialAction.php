<?php

namespace App\Actions\Billing;

use App\Models\Tenant;

class StartTenantTrialAction
{
    public function handle(Tenant $tenant): Tenant
    {
        $days = (int) config('billing.trial_days', 14);

        $tenant->forceFill([
            'trial_ends_at' => now()->addDays($days),
            'billing_plan' => null,
            'billing_active_until' => null,
            'is_active' => true,
        ])->save();

        return $tenant->fresh();
    }
}
