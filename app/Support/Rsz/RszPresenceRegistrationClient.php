<?php

declare(strict_types=1);

namespace App\Support\Rsz;

use App\Models\Tenant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Dunne HTTP-client voor RSZ Check In and Out at Work (presenceRegistration).
 * Alleen aanroepen vanuit Actions/Jobs.
 */
final class RszPresenceRegistrationClient
{
    public function baseUrl(): string
    {
        return config('rsz.use_simulation')
            ? (string) config('rsz.simulation_base_url')
            : (string) config('rsz.production_base_url');
    }

    public function tokenUrl(): string
    {
        return config('rsz.use_simulation')
            ? (string) config('rsz.oauth_token_url_simulation')
            : (string) config('rsz.oauth_token_url');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function registerInBulk(Tenant $tenant, array $items): array
    {
        $token = $this->accessToken($tenant);

        $response = $this->http($token)
            ->post($this->baseUrl().'/presenceRegistrations/registerInBulk', [
                'items' => $items,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'rsz_register_failed:'.$response->status().':'.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    public function accessToken(Tenant $tenant): string
    {
        $static = config('rsz.static_access_token');
        if (is_string($static) && $static !== '') {
            return $static;
        }

        $clientId = $tenant->presence_rsz_client_id;
        $privateKey = $tenant->presence_rsz_private_key;

        if (! is_string($clientId) || $clientId === '' || ! is_string($privateKey) || $privateKey === '') {
            throw new RuntimeException('rsz_credentials_missing');
        }

        $assertion = $this->createClientAssertion($clientId, $privateKey, $this->tokenUrl());

        $response = Http::asForm()
            ->timeout((int) config('rsz.timeout_seconds', 15))
            ->post($this->tokenUrl(), [
                'grant_type' => 'client_credentials',
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'rsz_token_failed:'.$response->status().':'.$response->body()
            );
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('rsz_token_empty');
        }

        return $token;
    }

    private function http(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('rsz.timeout_seconds', 15));
    }

    private function createClientAssertion(string $clientId, string $privateKeyPem, string $audience): string
    {
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $clientId,
            'sub' => $clientId,
            'aud' => $audience,
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $data = $header.'.'.$payload;
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new RuntimeException('rsz_private_key_invalid');
        }

        $signature = '';
        $ok = openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new RuntimeException('rsz_jwt_sign_failed');
        }

        return $data.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
