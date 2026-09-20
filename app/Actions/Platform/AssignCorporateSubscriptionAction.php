<?php

namespace App\Actions\Platform;

use App\Models\Tenant;
use App\Models\User;

class AssignCorporateSubscriptionAction
{
    public function __construct(private AssignTenantSubscriptionPlanAction $assign) {}

    public function handle(Tenant $tenant, int $unitsCap, User $actor): Tenant
    {
        return $this->assign->handle($tenant, 'corporate', $actor, $unitsCap);
    }
}
