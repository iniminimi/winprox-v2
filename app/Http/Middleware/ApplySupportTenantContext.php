<?php

namespace App\Http\Middleware;

use App\Support\Platform\SupportTenantContext;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zet Tenancy::actAs() wanneer een superuser een support view actief heeft.
 */
class ApplySupportTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->is_superuser && $user->tenant_id === null) {
            $tenantId = SupportTenantContext::activeTenantId();

            if ($tenantId !== null) {
                Tenancy::actAs($tenantId);
            } else {
                Tenancy::forget();
            }
        }

        return $next($request);
    }
}
