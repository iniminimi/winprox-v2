<?php

namespace App\Actions\Billing;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

class ActivateSubscriptionPlanAction
{
    public function handle(User $actor, Tenant $tenant, string $plan): Tenant
    {
        $periodDays = (int) config('billing.subscription_period_days', 365);

        $tenant->forceFill([
            'billing_plan' => $plan,
            'billing_active_until' => Carbon::now()->addDays($periodDays),
            'trial_ends_at' => now(),
            'is_active' => true,
        ])->save();

        return $tenant->fresh();
    }
}
