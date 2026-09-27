<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ClockPoint;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Device-auth voor klokschermen (wpclk_-token in X-WinProx-Clock-Key of Bearer),
 * spiegel van AuthenticateIotGateway. Zet Tenancy + request-attribuut
 * `clock_display_point`. 401 → het scherm wist zijn credentials en valt
 * terug naar pairing-modus (diefstal/onkoppeling/re-pair).
 */
class AuthenticateClockDisplay
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $clockPoint = ClockPoint::withoutGlobalScope('tenant')
            ->where('display_token_hash', hash('sha256', $token))
            ->first();

        if ($clockPoint === null || ! $clockPoint->matchesDisplayToken($token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tenant = $clockPoint->tenant;
        if ($tenant === null || ! $tenant->is_active || ! $tenant->hasTimeModule()) {
            return response()->json(['message' => 'Time module disabled'], 403);
        }

        if (! $tenant->hasFullAppAccess()) {
            return response()->json(['message' => 'Subscription inactive'], 403);
        }

        Tenancy::actAs((int) $clockPoint->tenant_id);
        $request->attributes->set('clock_display_point', $clockPoint);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = (string) $request->header('X-WinProx-Clock-Key', '');
        if ($header !== '') {
            return $header;
        }

        $bearer = $request->bearerToken();

        return is_string($bearer) && $bearer !== '' ? $bearer : null;
    }
}
