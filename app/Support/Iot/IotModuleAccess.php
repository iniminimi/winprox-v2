<?php

declare(strict_types=1);

namespace App\Support\Iot;

use App\Models\Tenant;
use App\Support\Tenancy;

final class IotModuleAccess
{
    public static function tenantHasModule(?Tenant $tenant): bool
    {
        return $tenant !== null && $tenant->hasIotModule();
    }

    public static function activeTenantHasModule(): bool
    {
        $tenantId = Tenancy::id();

        if ($tenantId === null) {
            return false;
        }

        $tenant = Tenant::query()->find($tenantId);

        return self::tenantHasModule($tenant);
    }
}
