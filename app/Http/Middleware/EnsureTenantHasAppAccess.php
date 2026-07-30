<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantHasAppAccess
{
    /** @var list<string> */
    protected array $allowedRoutePatterns = [
        'subscription.*',
        'faq.*',
        'contact.*',
        'account.*',
        'platform.*',
        'legal.*',
        'product.*',
        'logout',
        'livewire.*',
        'locale.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->is_superuser) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if ($tenant === null || $tenant->hasFullAppAccess()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName !== null && $this->routeIsAllowed($routeName)) {
            return $next($request);
        }

        return redirect()->route('subscription.index');
    }

    protected function routeIsAllowed(string $routeName): bool
    {
        foreach ($this->allowedRoutePatterns as $pattern) {
            if ($pattern === $routeName) {
                return true;
            }

            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                if (str_starts_with($routeName, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
