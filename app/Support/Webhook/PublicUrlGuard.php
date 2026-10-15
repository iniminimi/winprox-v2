<?php

namespace App\Support\Webhook;

use InvalidArgumentException;

/**
 * SSRF-guard voor outbound webhooks: alleen http(s) naar publieke hosts.
 * Host wordt opgelost; ALLE gevonden IP's moeten publiek zijn (anders kan
 * één DNS-record alsnog naar interne infra wijzen). Wordt opnieuw uitgevoerd
 * bij elke delivery — beperkt DNS-rebinding.
 */
final class PublicUrlGuard
{
    public static function assertAllowed(string $url): void
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('webhook_url_scheme_not_allowed');
        }

        if (isset($parts['user'], $parts['pass'])) {
            throw new InvalidArgumentException('webhook_url_credentials_not_allowed');
        }

        // Buiten productie zijn private hosts toegestaan: lokale dev-webhooks
        // naar localhost en test-fakes moeten blijven werken.
        if (! app()->environment('production')) {
            return;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : self::resolveIps($host);

        if ($ips === []) {
            throw new InvalidArgumentException('webhook_url_host_unresolvable');
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new InvalidArgumentException('webhook_url_private_host_not_allowed');
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function resolveIps(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records)) {
            return [];
        }

        $ips = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
