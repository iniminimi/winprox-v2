<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFacilityAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = auth()->user()?->tenant;

        if ($tenant instanceof Tenant && ! $tenant->hasFacilityAccess()) {
            return redirect()
                ->route('dashboard')
                ->with('error', __('subscription.facility_not_included'));
        }

        return $next($request);
    }
}
