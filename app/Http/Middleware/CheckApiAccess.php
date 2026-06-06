<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = Tenancy::id();

        if (! $tenantId) {
            return response()->json([
                'message' => 'API-toegang en webhooks zijn niet beschikbaar tijdens de proefperiode. Upgrade je abonnement voor volledige integratiemogelijkheden.',
            ], 403);
        }

        $tenant = \App\Models\Tenant::find($tenantId);

        if (! $tenant || ! $tenant->hasApiAccess()) {
            return response()->json([
                'message' => 'API-toegang en webhooks zijn niet beschikbaar tijdens de proefperiode. Upgrade je abonnement voor volledige integratiemogelijkheden.',
            ], 403);
        }

        return $next($request);
    }
}
