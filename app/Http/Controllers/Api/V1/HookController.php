<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Voorbeeld van een geverifieerde inkomende hook (integration-first).
 * Uitbreiden per externe integratie via eigen Action.
 */
class HookController extends Controller
{
    public function inbound(Request $request): JsonResponse
    {
        $secret = (string) config('services.winprox.inbound_hook_secret', '');
        $signature = (string) $request->header('X-WinProx-Signature', '');
        $timestamp = (string) $request->header('X-WinProx-Timestamp', '');
        $body = $request->getContent();

        if ($secret === '' || $signature === '' || $timestamp === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        return response()->json(['data' => ['received' => true]]);
    }
}
