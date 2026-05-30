<?php

namespace App\Actions\Platform;

use App\Support\Platform\SupportTenantContext;
use App\Support\Tenancy;

final class StopSupportViewAction
{
    public function handle(): void
    {
        SupportTenantContext::stop();
        Tenancy::forget();
    }
}
