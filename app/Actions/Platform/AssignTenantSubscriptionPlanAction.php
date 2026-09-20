<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Models\Tenant;
use App\Models\User;
use InvalidArgumentException;

class AssignTenantSubscriptionPlanAction
{
    public function __construct(private ActivateSubscriptionPlanAction $activate) {}

    public function handle(Tenant $tenant, string $plan, User $actor, ?int $unitsCap = null): Tenant
    {
        $plan = Tenant::normalizeBillingPlanKey($plan) ?? $plan;

        if (! is_array(config("billing.plans.{$plan}"))) {
            throw new InvalidArgumentException('unknown_plan');
        }

        if ($plan === 'corporate' && ($unitsCap === null || $unitsCap < 1)) {
            throw new InvalidArgumentException('corporate_units_cap_required');
        }

        return $this->activate->handle(
            actor: $actor,
            tenant: $tenant,
            plan: $plan,
            source: 'platform',
            unitsCap: $plan === 'corporate' ? $unitsCap : null,
        );
    }
}
