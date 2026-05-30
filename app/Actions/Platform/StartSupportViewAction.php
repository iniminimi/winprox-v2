<?php

namespace App\Actions\Platform;

use App\Models\Tenant;
use App\Support\Platform\SupportTenantContext;

final class StartSupportViewAction
{
    public function handle(Tenant $tenant): void
    {
        SupportTenantContext::start((int) $tenant->id);
    }
}
