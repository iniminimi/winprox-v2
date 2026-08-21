<?php

declare(strict_types=1);

namespace App\Support\Marketing;

/**
 * Cheap domain check: no MX (or only null MX) means mail will bounce.
 * Does not prove that a mailbox exists on a domain that does accept mail.
 */
final class PromoEmailMxLookup
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function domainAcceptsMail(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '' || ! str_contains($domain, '.') || filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        if (array_key_exists($domain, $this->cache)) {
            return $this->cache[$domain];
        }

        $overrides = config('winprox.promo_email_mx_overrides', []);
        if (is_array($overrides) && array_key_exists($domain, $overrides)) {
            return $this->cache[$domain] = (bool) $overrides[$domain];
        }

        if (! (bool) config('winprox.promo_email_preflight_dns', true)) {
            return $this->cache[$domain] = true;
        }

        return $this->cache[$domain] = $this->lookup($domain);
    }

    private function lookup(string $domain): bool
    {
        $mx = @dns_get_record($domain, DNS_MX);
        if ($mx === false) {
            return true;
        }

        $hasUsableMx = false;
        foreach ($mx as $record) {
            $target = strtolower(rtrim((string) ($record['target'] ?? ''), '.'));
            if ($target !== '' && $target !== '.') {
                $hasUsableMx = true;

                break;
            }
        }

        if ($hasUsableMx) {
            return true;
        }

        if ($mx !== []) {
            return false;
        }

        $a = @dns_get_record($domain, DNS_A);
        $aaaa = @dns_get_record($domain, DNS_AAAA);
        if ($a === false || $aaaa === false) {
            return true;
        }

        return $a !== [] || $aaaa !== [];
    }

    private function normalizeDomain(string $domain): string
    {
        return strtolower(rtrim(trim($domain), '.'));
    }
}
