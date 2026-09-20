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
 *
 * OAuth: bij voorkeur WinProx-platform Chaman-credentials (config/rsz.php).
 * Optionele tenant-override blijft mogelijk voor uitzonderingen.
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

        [$clientId, $privateKey] = $this->resolveCredentials($tenant);

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

    /**
     * @return array{0: string, 1: string} clientId, privateKeyPem
     */
    public function resolveCredentials(Tenant $tenant): array
    {
        $platformClientId = trim((string) config('rsz.platform_client_id', ''));
        $platformKey = $this->platformPrivateKeyPem();

        // Productpad: WinProx-platform Chaman. Tenant-override alleen als platform ontbreekt.
        if ($platformClientId !== '' && $platformKey !== '') {
            return [$platformClientId, $platformKey];
        }

        if ($platformKey !== '' && $platformClientId === '') {
            throw new RuntimeException('rsz_platform_client_id_missing');
        }

        $tenantClientId = is_string($tenant->presence_rsz_client_id) ? trim($tenant->presence_rsz_client_id) : '';
        $tenantKey = is_string($tenant->presence_rsz_private_key) ? trim($tenant->presence_rsz_private_key) : '';

        if ($tenantClientId !== '' && $tenantKey !== '') {
            return [$tenantClientId, $tenantKey];
        }

        throw new RuntimeException('rsz_credentials_missing');
    }

    public function platformCredentialsConfigured(): bool
    {
        return trim((string) config('rsz.platform_client_id', '')) !== ''
            && $this->platformPrivateKeyPem() !== '';
    }

    private function platformPrivateKeyPem(): string
    {
        $path = trim((string) config('rsz.platform_private_key_path', ''));
        if ($path !== '') {
            if (! is_readable($path)) {
                throw new RuntimeException('rsz_platform_private_key_unreadable');
            }

            $contents = file_get_contents($path);
            if (! is_string($contents) || trim($contents) === '') {
                throw new RuntimeException('rsz_platform_private_key_empty');
            }

            return trim($contents);
        }

        $raw = (string) config('rsz.platform_private_key', '');
        if ($raw === '') {
            return '';
        }

        return trim(str_replace('\\n', "\n", $raw));
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
