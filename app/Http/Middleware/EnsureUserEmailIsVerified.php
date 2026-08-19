<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Beheerschermen zijn pas bruikbaar na e-mailverificatie. Publieke pagina's (marketing,
 * QR-portalen) blijven open: we controleren alleen routes achter `auth`.
 */
class EnsureUserEmailIsVerified
{
    /** @var list<string> */
    protected array $allowedRoutePatterns = [
        'verification.*',
        'legal.*',
        'product.*',
        'faq.*',
        'logout',
        'livewire.*',
        'locale.*',
        'ui-theme.switch',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_superuser || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        $route = $request->route();

        if ($route === null || ! in_array('auth', $route->gatherMiddleware(), true)) {
            return $next($request);
        }

        $routeName = $route->getName();

        if ($routeName !== null && $this->routeIsAllowed($routeName)) {
            return $next($request);
        }

        return redirect()->route('verification.notice');
    }

    protected function routeIsAllowed(string $routeName): bool
    {
        foreach ($this->allowedRoutePatterns as $pattern) {
            if ($pattern === $routeName) {
                return true;
            }

            if (str_ends_with($pattern, '.*')
                && str_starts_with($routeName, substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
