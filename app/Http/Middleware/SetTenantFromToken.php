<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zet de tenant-context (global scope) op basis van de token-eigenaar.
 *
 * - Gewone gebruiker: scope filtert op zijn tenant_id (nooit cross-tenant lekken).
 * - Superuser zonder tenant: geen context → ziet alles (platformbeheer, §8).
 *
 * Draait na auth:sanctum zodat $request->user() beschikbaar is.
 */
class SetTenantFromToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->tenant_id !== null) {
            Tenancy::actAs((int) $user->tenant_id);
        }

        return $next($request);
    }
}
