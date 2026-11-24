<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SensePoint;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSensePoint
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $point = SensePoint::query()
            ->where('token_hash', SensePoint::hashToken($token))
            ->first();

        if ($point === null || ! $point->matchesToken($token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tenant = $point->tenant;
        if ($tenant === null || ! $tenant->is_active || CheckmateMode::isActive($tenant)) {
            return response()->json(['message' => 'Sense disabled'], 403);
        }

        if (! $tenant->hasFullAppAccess()) {
            return response()->json(['message' => 'Subscription inactive'], 403);
        }

        Tenancy::actAs((int) $point->tenant_id);
        $request->attributes->set('sense_point', $point);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = (string) $request->header('X-WinProx-Sense-Key', '');
        if ($header !== '') {
            return $header;
        }

        $bearer = $request->bearerToken();

        return is_string($bearer) && $bearer !== '' ? $bearer : null;
    }
}
