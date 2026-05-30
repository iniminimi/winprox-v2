<?php

namespace App\Http\Middleware;

use App\Support\Platform\SupportTenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Superusers zonder actieve support view mogen geen facility-schermen openen.
 */
class RequireSupportTenantForSuperuser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->is_superuser && $user->tenant_id === null && ! SupportTenantContext::isActive()) {
            return redirect()->route('platform.tenants');
        }

        return $next($request);
    }
}
