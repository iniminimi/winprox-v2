<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Camera;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCamera
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $camera = Camera::query()
            ->withoutGlobalScopes()
            ->where('token_hash', Camera::hashToken($token))
            ->first();

        if ($camera === null || ! $camera->matchesToken($token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tenant = $camera->tenant;
        if ($tenant === null || ! $tenant->is_active || CheckmateMode::isActive($tenant)) {
            return response()->json(['message' => 'Camera disabled'], 403);
        }

        if (! $tenant->hasFullAppAccess()) {
            return response()->json(['message' => 'Subscription inactive'], 403);
        }

        Tenancy::actAs((int) $camera->tenant_id);
        $request->attributes->set('camera', $camera);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = (string) $request->header('X-WinProx-Camera-Key', '');
        if ($header !== '') {
            return $header;
        }

        $bearer = $request->bearerToken();

        return is_string($bearer) && $bearer !== '' ? $bearer : null;
    }
}
