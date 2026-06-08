<?php

namespace App\Support\Portal;

use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Unit;

/**
 * Toegang/gating voor de publieke QR-portalen. Bij inactiviteit zijn alle acties
 * no-op en toont het portaal een gelokaliseerde reden. Onbekende tokens → 404.
 */
final class PortalAccess
{
    /**
     * @return null|string Vertaalsleutel onder portal.inactive.*
     */
    public static function unitPortalInactiveReasonKey(Unit $unit): ?string
    {
        $unit->loadMissing(['location', 'category.teams', 'tenant']);

        if ($reason = self::tenantInactiveReasonKey($unit->tenant)) {
            return $reason;
        }

        if ($unit->location === null || ! $unit->location->is_active) {
            return 'portal.inactive.location_inactive';
        }

        if (! $unit->is_active) {
            return 'portal.inactive.unit_inactive';
        }

        if ($unit->category !== null) {
            $team = $unit->category->teams()->first();
            if ($team !== null && ! $team->is_active) {
                return 'portal.inactive.team_inactive';
            }
        }

        return null;
    }

    public static function isUnitPortalOpen(Unit $unit): bool
    {
        return self::unitPortalInactiveReasonKey($unit) === null;
    }

    /**
     * @return null|string Vertaalsleutel onder portal.inactive.*
     */
    public static function teamPortalInactiveReasonKey(InternalTeam $team): ?string
    {
        $team->loadMissing('tenant');

        if ($reason = self::tenantInactiveReasonKey($team->tenant)) {
            return $reason;
        }

        if (! $team->is_active) {
            return 'portal.inactive.team_inactive';
        }

        return null;
    }

    /**
     * @return null|string Vertaalsleutel onder portal.inactive.*
     */
    private static function tenantInactiveReasonKey(?Tenant $tenant): ?string
    {
        if ($tenant === null) {
            return 'portal.inactive.tenant_inactive';
        }

        if (! $tenant->is_active) {
            return 'portal.inactive.tenant_inactive';
        }

        if (! $tenant->hasFullAppAccess()) {
            return 'portal.inactive.subscription_inactive';
        }

        return null;
    }

    public static function isTeamPortalOpen(InternalTeam $team): bool
    {
        return self::teamPortalInactiveReasonKey($team) === null;
    }

}
