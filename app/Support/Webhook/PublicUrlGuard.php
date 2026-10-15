<?php

namespace App\Support\Webhook;

use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * SSRF-guard voor outbound webhooks.
 *
 * Eén DNS-lookup (A + AAAA). Is één adres privé of reserved, dan is de hele
 * naam afgekeurd. Het goedgekeurde IP gaat via CURLOPT_RESOLVE de request in,
 * zodat curl niet nog eens resolvet. Redirects staan uit.
 *
 * Private hosts alleen in local en testing. Elke andere APP_ENV volgt de
 * productieregels.
 *
 * dns_get_record vraagt DNS rechtstreeks en negeert /etc/hosts. Dat wijkt af
 * van de systeemresolver; de pin dwingt dit IP af, dus de hosts-file kan de
 * verbinding niet sturen.
 */
final class PublicUrlGuard
{
    public const MAX_RESPONSE_BYTES = 65536;

    public const CONNECT_TIMEOUT_SECONDS = 5;

    public const TIMEOUT_SECONDS = 15;

    /** @var list<string> */
    private const PRIVATE_HOST_ENVIRONMENTS = ['local', 'testing'];

    /** @var list<array{0: string, 1: int}> */
    private const IPV4_BLOCKS = [
        ['0.0.0.0', 8],
        ['10.0.0.0', 8],
        ['100.64.0.0', 10],
        ['127.0.0.0', 8],
        ['169.254.0.0', 16],
        ['172.16.0.0', 12],
        ['192.0.0.0', 24],
        ['192.168.0.0', 16],
        ['198.18.0.0', 15],
        ['224.0.0.0', 4],
        ['240.0.0.0', 4],
    ];

    /** @var list<array{0: string, 1: int}> */
    private const IPV6_BLOCKS = [
        ['fc00::', 7],
        ['fe80::', 10],
        ['64:ff9b::', 96],
        ['2002::', 16],
        ['2001::', 32],
        ['ff00::', 8],
    ];

    /** @var (callable(string): list<string>)|null */
    private static $dnsResolver = null;

    /**
     * @param  (callable(string): list<string>)|null  $resolver
     */
    public static function useDnsResolver(?callable $resolver): void
    {
        self::$dnsResolver = $resolver;
    }

    public static function assertAllowed(string $url): void
    {
        self::inspect($url);
    }

    /**
     * @return array{url: string, options: array<string, mixed>}
     */
    public static function deliveryOptions(string $url): array
    {
        $target = self::inspect($url);

        $options = [
            'allow_redirects' => false,
            'proxy' => '',
            'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
            'timeout' => self::TIMEOUT_SECONDS,
            'stream' => true,
            'sink' => self::responseSink(),
            'on_headers' => static function (ResponseInterface $response): void {
                $length = $response->getHeaderLine('Content-Length');
                if ($length !== '' && ctype_digit($length) && (int) $length > self::MAX_RESPONSE_BYTES) {
                    throw new RuntimeException('webhook_response_too_large');
                }
            },
        ];

        if ($target['ip'] !== null) {
            $options['curl'] = [
                CURLOPT_RESOLVE => [
                    self::curlResolveEntry($target['host'], $target['port'], $target['ip']),
                ],
            ];
        }

        return [
            'url' => $target['url'],
            'options' => $options,
        ];
    }

    /**
     * @return array{url: string, host: string, port: int, ip: ?string}
     */
    private static function inspect(string $url): array
    {
        $original = trim($url);
        $parts = parse_url($original);
        $scheme = strtolower((string) (is_array($parts) ? ($parts['scheme'] ?? '') : ''));
        $rawHost = (string) (is_array($parts) ? ($parts['host'] ?? '') : '');

        if (! in_array($scheme, ['http', 'https'], true) || $rawHost === '') {
            throw new InvalidArgumentException('webhook_url_scheme_not_allowed');
        }

        if (is_array($parts) && (isset($parts['user']) || isset($parts['pass']))) {
            throw new InvalidArgumentException('webhook_url_credentials_not_allowed');
        }

        $host = self::normalizeHost($rawHost);

        if (self::isDisguisedAddress($host)) {
            throw new InvalidArgumentException('webhook_url_host_not_allowed');
        }

        $port = is_array($parts) && isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('webhook_url_port_not_allowed');
        }

        $allowsPrivate = self::allowsPrivateHosts();

        if (! $allowsPrivate && ! in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('webhook_url_port_not_allowed');
        }

        $ip = null;

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (! $allowsPrivate && self::isBlockedIp($host)) {
                throw new InvalidArgumentException('webhook_url_private_host_not_allowed');
            }
        } elseif (! $allowsPrivate) {
            $ip = self::pinnedIp($host);
        }

        return [
            'url' => $host === $rawHost ? $original : self::rebuildUrl($scheme, $host, $port, is_array($parts) ? $parts : []),
            'host' => $host,
            'port' => $port,
            'ip' => $ip,
        ];
    }

    private static function normalizeHost(string $host): string
    {
        // PHP op Windows laat de haken om een IPv6-literal in parse_url staan.
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if (str_ends_with($host, '.')) {
            $host = substr($host, 0, -1);
        }

        if (preg_match('/[^\x00-\x7F]/', $host) !== 1) {
            return $host;
        }

        if (! function_exists('idn_to_ascii')) {
            throw new InvalidArgumentException('webhook_url_host_not_allowed');
        }

        $ascii = idn_to_ascii($host, IDNA_DEFAULT);
        if (! is_string($ascii) || $ascii === '') {
            throw new InvalidArgumentException('webhook_url_host_not_allowed');
        }

        return $ascii;
    }

    /**
     * Curl accepteert decimale, octale en hex-vormen (2130706433, 0x7f.1, 127.1)
     * als adres. Alleen filter_var(..., FILTER_VALIDATE_IP) telt als literal;
     * de rest wordt afgewezen, ook zonder DNS.
     */
    private static function isDisguisedAddress(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        if (preg_match('/^[0-9a-fx.]+$/i', $host) !== 1 || preg_match('/\d/', $host) !== 1) {
            return false;
        }

        foreach (explode('.', $host) as $label) {
            if ($label === '' || preg_match('/^(?:0x[0-9a-f]+|\d+)$/i', $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    private static function pinnedIp(string $host): string
    {
        $ips = self::lookupIps($host);

        if ($ips === []) {
            throw new InvalidArgumentException('webhook_url_host_unresolvable');
        }

        foreach ($ips as $ip) {
            if (self::isBlockedIp($ip)) {
                throw new InvalidArgumentException('webhook_url_private_host_not_allowed');
            }
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $ip;
            }
        }

        return $ips[0];
    }

    /**
     * @return list<string>
     */
    private static function lookupIps(string $host): array
    {
        if (self::$dnsResolver !== null) {
            return array_values(array_filter((self::$dnsResolver)($host), 'is_string'));
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records)) {
            return [];
        }

        $ipv4 = [];
        $ipv6 = [];

        foreach ($records as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $ipv4[] = $record['ip'];
            } elseif (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ipv6[] = $record['ipv6'];
            }
        }

        return array_values(array_unique([...$ipv4, ...$ipv6]));
    }

    private static function isBlockedIp(string $ip): bool
    {
        $mapped = self::unwrapMappedIpv4($ip);
        if ($mapped !== null) {
            return self::isBlockedIpv4($mapped);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::isBlockedIpv4($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return self::isBlockedIpv6($ip);
        }

        return true;
    }

    private static function unwrapMappedIpv4(string $ip): ?string
    {
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        if (substr($packed, 0, 12) !== "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
            return null;
        }

        $v4 = inet_ntop(substr($packed, 12, 4));

        return is_string($v4) ? $v4 : null;
    }

    private static function isBlockedIpv4(string $ip): bool
    {
        foreach (self::IPV4_BLOCKS as [$network, $bits]) {
            if (self::ipInCidr($ip, $network, $bits)) {
                return true;
            }
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    private static function isBlockedIpv6(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return true;
        }

        if ($packed === inet_pton('::') || $packed === inet_pton('::1')) {
            return true;
        }

        foreach (self::IPV6_BLOCKS as [$network, $bits]) {
            if (self::ipInCidr($ip, $network, $bits)) {
                return true;
            }
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    private static function ipInCidr(string $ip, string $network, int $bits): bool
    {
        $ipBin = @inet_pton($ip);
        $networkBin = @inet_pton($network);

        if ($ipBin === false || $networkBin === false || strlen($ipBin) !== strlen($networkBin)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($networkBin, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBin[$bytes]) & $mask) === (ord($networkBin[$bytes]) & $mask);
    }

    private static function allowsPrivateHosts(): bool
    {
        return in_array((string) app()->environment(), self::PRIVATE_HOST_ENVIRONMENTS, true);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private static function rebuildUrl(string $scheme, string $host, int $port, array $parts): string
    {
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $authority = str_contains($host, ':') ? '['.$host.']' : $host;

        if ($port !== $defaultPort) {
            $authority .= ':'.$port;
        }

        $rebuilt = $scheme.'://'.$authority.($parts['path'] ?? '');

        if (isset($parts['query']) && is_string($parts['query'])) {
            $rebuilt .= '?'.$parts['query'];
        }

        return $rebuilt;
    }

    private static function curlResolveEntry(string $host, int $port, string $ip): string
    {
        $address = str_contains($ip, ':') ? '['.$ip.']' : $ip;

        return $host.':'.$port.':'.$address;
    }

    private static function responseSink(): WebhookResponseSink
    {
        return new WebhookResponseSink(Utils::streamFor(Utils::tryFopen('php://temp', 'w+')));
    }
}

/**
 * Kapt de response-body op 64 KiB, ook zonder Content-Length (chunked).
 * Guzzle schrijft via deze sink; een te grote write breekt de transfer af.
 */
final class WebhookResponseSink implements StreamInterface
{
    use \GuzzleHttp\Psr7\StreamDecoratorTrait;

    private StreamInterface $stream;

    private int $written = 0;

    public function write(string $string): int
    {
        $this->written += strlen($string);

        if ($this->written > PublicUrlGuard::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('webhook_response_too_large');
        }

        return $this->stream->write($string);
    }
}
