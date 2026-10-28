<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uurrooster en wat daaraan hangt. Prikklok en QR-aanmelding blijven.
 */
class EnsureTimeRosterEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = Tenancy::id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;

        abort_unless($tenant instanceof Tenant && $tenant->timeRosterEnabled(), 403);

        return $next($request);
    }
}
