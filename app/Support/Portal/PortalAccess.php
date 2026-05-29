<?php

namespace App\Support\Portal;

use App\Models\InternalTeam;
use App\Models\Unit;

/**
 * Toegang/gating voor de publieke QR-portalen. Bij inactiviteit zijn alle acties
 * no-op en toont het portaal een gelokaliseerde reden. Onbekende tokens → 404.
 *
 * Tenant-niveau (abonnement/billing) bestaat nog niet in het V2-schema en wordt
 * hier daarom niet gecontroleerd.
 */
final class PortalAccess
{
    /**
     * @return null|string Vertaalsleutel onder portal.inactive.*
     */
    public static function unitPortalInactiveReasonKey(Unit $unit): ?string
    {
        $unit->loadMissing('location');

        if ($unit->location === null || ! $unit->location->is_active) {
            return 'portal.inactive.location_inactive';
        }

        if (! $unit->is_active) {
            return 'portal.inactive.unit_inactive';
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
        if (! $team->is_active) {
            return 'portal.inactive.team_inactive';
        }

        return null;
    }

    public static function isTeamPortalOpen(InternalTeam $team): bool
    {
        return self::teamPortalInactiveReasonKey($team) === null;
    }
}
