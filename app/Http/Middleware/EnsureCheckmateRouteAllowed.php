<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Platform\SupportTenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Whitelist-gating voor Checkmate-tenants (docs/CHECKMATE.md §2): niet- whitelist
 * admin-routes geven 404 — een feature is onzichtbaar tenzij expliciet toegestaan.
 */
class EnsureCheckmateRouteAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $tenant = $this->effectiveTenant($user);

        if (! CheckmateMode::isActive($tenant)) {
            return $next($request);
        }

        if (! CheckmateMode::adminRouteAllowed($request->route()?->getName())) {
            abort(404);
        }

        return $next($request);
    }

    private function effectiveTenant(\App\Models\User $user): ?Tenant
    {
        if ($user->is_superuser && $user->tenant_id === null) {
            if (! SupportTenantContext::isActive()) {
                return null;
            }

            return Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        return $user->tenant;
    }
}
