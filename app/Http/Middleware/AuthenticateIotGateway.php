<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IotGateway;
use App\Support\Iot\IotModuleAccess;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIotGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $gateway = IotGateway::query()
            ->where('token_hash', IotGateway::hashToken($token))
            ->where('is_active', true)
            ->first();

        if ($gateway === null || ! $gateway->matchesToken($token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tenant = $gateway->tenant;
        if (! IotModuleAccess::tenantHasModule($tenant) || $tenant === null || ! $tenant->is_active) {
            return response()->json(['message' => 'IoT Connect disabled'], 403);
        }

        if (! $tenant->hasFullAppAccess()) {
            return response()->json(['message' => 'Subscription inactive'], 403);
        }

        Tenancy::actAs((int) $gateway->tenant_id);
        $request->attributes->set('iot_gateway', $gateway);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = (string) $request->header('X-WinProx-Iot-Key', '');
        if ($header !== '') {
            return $header;
        }

        $bearer = $request->bearerToken();

        return is_string($bearer) && $bearer !== '' ? $bearer : null;
    }
}
