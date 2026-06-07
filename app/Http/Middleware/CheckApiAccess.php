<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Bepaal het abonnement en de bijbehorende limieten
        $plan = $tenant->effectivePlanKey() ?? 'trial';
        $limits = config("billing.api_rate_limits.{$plan}", ['max_attempts' => 60, 'decay_seconds' => 60]);

        // Genereer een unieke rate-limit key per tenant + IP
        $rateLimitKey = "api_limit:tenant_{$tenantId}:" . $request->ip();

        // Controleer de limiet
        if (RateLimiter::tooManyAttempts($rateLimitKey, $limits['max_attempts'])) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => 'Rate limit exceeded.',
                'retry_after_seconds' => $seconds
            ], 429, [
                'Retry-After' => $seconds,
                'X-RateLimit-Limit' => $limits['max_attempts'],
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => time() + $seconds,
            ]);
        }

        // Registreer de hit
        RateLimiter::hit($rateLimitKey, $limits['decay_seconds']);

        // Voer het request uit
        $response = $next($request);

        // Voeg Enterprise-headers toe aan de response
        $remaining = RateLimiter::remaining($rateLimitKey, $limits['max_attempts']);
        $response->headers->set('X-RateLimit-Limit', $limits['max_attempts']);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', time() + RateLimiter::availableIn($rateLimitKey));

        return $response;
    }
}
