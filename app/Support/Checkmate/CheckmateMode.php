<?php

namespace App\Support\Checkmate;

use App\Models\Tenant;

/**
 * Checkmate = plan-preset op dezelfde codebase (docs/CHECKMATE.md §2).
 * Whitelist-gating: niet-vermelde admin-routes en portaal-tegels zijn voor
 * Checkmate-tenants onzichtbaar; nieuwe features verschijnen pas na expliciete
 * toevoeging hier.
 */
final class CheckmateMode
{
    /**
     * Admin-routepatronen die een Checkmate-tenant mag gebruiken (§5).
     * Geldt alleen binnen de support.tenant-routegroep; routes daarbuiten
     * (faq, legal, account, logout, publieke pagina's) zijn nooit gated.
     * Alles daarbuiten → 404 (onzichtbaar, niet "geen rechten").
     *
     * @return list<string>
     */
    public static function allowedAdminRoutePatterns(): array
    {
        return [
            'dashboard',
            'customers.*',          // Klanten
            'workers.*',            // Uitvoerders
            'time.presence.*',      // Time: aanwezigheid
            'time.shifts.*',        // Time: uren
            'time.ciao.*',          // Time: CIAO-inzendingen
            'time.clock-points.*',  // Clock Point-links
            'settings.index',       // Instellingen
            'subscription.*',       // Abonnement (+ purge)
            'qr.connect',           // toestel-koppeling via QR
        ];
    }

    /**
     * Portaal-tegels op het Time-portaal (§2 whitelist):
     * klok, klantbezoek, pauze, mijn uren — geen taken/rooster/absentie/unitchecks.
     *
     * @return list<string>
     */
    public static function allowedPortalTiles(): array
    {
        return ['clock', 'visit', 'break', 'hours', 'customer_create'];
    }

    public static function isActive(?Tenant $tenant): bool
    {
        return $tenant !== null && $tenant->checkmateMode();
    }

    public static function adminRouteAllowed(?string $routeName): bool
    {
        if ($routeName === null) {
            return true;
        }

        foreach (self::allowedAdminRoutePatterns() as $pattern) {
            if ($pattern === $routeName) {
                return true;
            }

            if (str_ends_with($pattern, '.*') && str_starts_with($routeName, substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
