<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckTokenAbilities
{
    public function handle(Request $request, Closure $next, string $ability)
    {
        $user = Auth::user();
        $token = $user?->currentAccessToken();

        if ($token === null) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (! $token->can($ability) && ! $token->can('*')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
