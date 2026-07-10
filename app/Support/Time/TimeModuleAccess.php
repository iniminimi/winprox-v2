<?php

declare(strict_types=1);

namespace App\Support\Time;

use App\Models\Tenant;
use App\Support\Tenancy;
use InvalidArgumentException;

final class TimeModuleAccess
{
    public static function tenantHasModule(?Tenant $tenant): bool
    {
        return $tenant !== null && $tenant->hasTimeModule();
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

    public static function assertEnabledForTenantId(int $tenantId): void
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! self::tenantHasModule($tenant)) {
            throw new InvalidArgumentException('time_module_disabled');
        }
    }
}
