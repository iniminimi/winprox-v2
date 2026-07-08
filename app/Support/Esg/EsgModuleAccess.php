<?php

declare(strict_types=1);

namespace App\Support\Esg;

use App\Models\Tenant;
use App\Support\Tenancy;

final class EsgModuleAccess
{
    public static function tenantHasModule(?Tenant $tenant): bool
    {
        return $tenant !== null && $tenant->hasEsgModule();
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
