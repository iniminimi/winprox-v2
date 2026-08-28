<?php

declare(strict_types=1);

namespace App\Support\Tenant;

use App\Models\Tenant;
use App\Support\Tenancy;

final class TenantWorkMenuAccess
{
    public static function calendarEnabled(?Tenant $tenant): bool
    {
        return $tenant !== null && $tenant->workMenuCalendarEnabled();
    }

    public static function reservationsEnabled(?Tenant $tenant): bool
    {
        return $tenant !== null && $tenant->workMenuReservationsEnabled();
    }

    public static function inspectionRoundsEnabled(?Tenant $tenant): bool
    {
        return $tenant !== null && $tenant->workMenuInspectionRoundsEnabled();
    }

    public static function unitMeasurementsEnabled(?Tenant $tenant): bool
    {
        return $tenant !== null && $tenant->workMenuUnitMeasurementsEnabled();
    }

    public static function activeTenantCalendarEnabled(): bool
    {
        return self::calendarEnabled(self::resolveActiveTenant());
    }

    public static function activeTenantReservationsEnabled(): bool
    {
        return self::reservationsEnabled(self::resolveActiveTenant());
    }

    public static function activeTenantInspectionRoundsEnabled(): bool
    {
        return self::inspectionRoundsEnabled(self::resolveActiveTenant());
    }

    public static function activeTenantUnitMeasurementsEnabled(): bool
    {
        return self::unitMeasurementsEnabled(self::resolveActiveTenant());
    }

    /**
     * Turning a flag off is always allowed. New enablement requires the werkmenu item to be on,
     * unless the unit/category already had the flag (grandfather).
     */
    public static function maySetBooleanFlag(bool $tenantFeatureEnabled, bool $newValue, bool $currentValue): bool
    {
        if (! $newValue) {
            return true;
        }

        if ($currentValue) {
            return true;
        }

        return $tenantFeatureEnabled;
    }

    public static function mayEnableReservations(?Tenant $tenant, bool $newValue, bool $currentValue): bool
    {
        return self::maySetBooleanFlag(self::reservationsEnabled($tenant), $newValue, $currentValue);
    }

    public static function mayEnableUnitMeasurements(?Tenant $tenant, bool $newValue, bool $currentValue): bool
    {
        return self::maySetBooleanFlag(self::unitMeasurementsEnabled($tenant), $newValue, $currentValue);
    }

    public static function mayEnableCategoryReservable(?Tenant $tenant, bool $newValue, bool $currentValue): bool
    {
        return self::maySetBooleanFlag(self::reservationsEnabled($tenant), $newValue, $currentValue);
    }

    public static function mayEnableCategoryUnitMeasurements(?Tenant $tenant, bool $newValue, bool $currentValue): bool
    {
        return self::maySetBooleanFlag(self::unitMeasurementsEnabled($tenant), $newValue, $currentValue);
    }

    private static function resolveActiveTenant(): ?Tenant
    {
        $tenantId = Tenancy::id();

        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }
}
