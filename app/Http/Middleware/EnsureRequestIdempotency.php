<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Support\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Overslaan bij safe HTTP-methoden
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // 2. Controleer of de header aanwezig is
        $originalKey = $request->header('Idempotency-Key');
        if (blank($originalKey)) {
            return $next($request);
        }

        // 3. Validatie van de key lengte
        if (strlen($originalKey) > config('api.idempotency_max_key_length', 255)) {
            return response()->json(['message' => 'The Idempotency-Key header may not be greater than 255 characters.'], 422);
        }

        $tenantId = Tenancy::id() ?? 'global';
        $keyHash = hash('sha256', $originalKey);
        $cacheKey = "idempotency:{$tenantId}:{$keyHash}";

        try {
            // Controleer of de cache driver tags ondersteunt (zoals Redis)
            $cacheProvider = Cache::supportsTags()
                ? Cache::tags(['idempotency', "tenant:{$tenantId}"])
                : Cache::store();

            // 4. Check of het request al eerder is uitgevoerd
            if ($cacheProvider->has($cacheKey)) {
                $cachedData = $cacheProvider->get($cacheKey);

                // Validatie: Is het een exacte match (anti-collision check)
                if ($cachedData['method'] !== $request->method() ||
                    $cachedData['url'] !== $request->fullUrl() ||
                    $cachedData['params'] !== $request->all()) {

                    return response()->json([
                        'message' => 'Conflict. This Idempotency-Key is already used for a request with different parameters.'
                    ], 409);
                }

                // Replay de opgeslagen response
                return response(
                    $cachedData['response_body'],
                    $cachedData['response_status'],
                    [
                        'Content-Type' => 'application/json',
                        'Idempotency-Key' => $originalKey,
                        'Idempotency-Replayed' => 'true'
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Fail-open: Log het probleem, maar breek de applicatieflow niet af
            Log::warning('Idempotency cache failure (Fail-open triggered): ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'key_hash' => $keyHash
            ]);
            return $next($request);
        }

        // 5. Voer het originele request uit
        $response = $next($request);

        // 6. Sla de response op onder strikte voorwaarden
        if ($response->isSuccessful() || $response->isClientError()) {
            $content = $response->getContent();

            // Alleen cachen als het geen streaming/file response is en < 1MB
            if (is_string($content) && strlen($content) <= config('api.idempotency_max_response_size', 1024 * 1024)) {
                try {
                    $cacheData = [
                        'original_key' => $originalKey,
                        'method' => $request->method(),
                        'url' => $request->fullUrl(),
                        'params' => $request->all(),
                        'response_status' => $response->getStatusCode(),
                        'response_body' => $content,
                        'created_at' => now()->timestamp,
                    ];

                    $ttl = config('api.idempotency_ttl', 86400);
                    $cacheProvider->put($cacheKey, $cacheData, $ttl);
                } catch (\Throwable $e) {
                    Log::warning('Failed to store idempotency response in cache: ' . $e->getMessage());
                }
            }
        }

        // Voeg de header toe aan de allereerste succesvolle response
        $response->headers->set('Idempotency-Key', $originalKey);

        return $response;
    }
}
