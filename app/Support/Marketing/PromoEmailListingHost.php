<?php

declare(strict_types=1);

namespace App\Support\Marketing;

/**
 * Scraped hotel-directory URLs often become info@{slug}.{city}hotels.{tld}.
 * Wildcard DNS makes MX look valid even though no hotel mailbox exists.
 */
final class PromoEmailListingHost
{
    /** @var list<string> */
    private const MULTI_PART_TLDS = [
        'com.es',
        'co.uk',
        'org.uk',
        'com.mx',
        'co.nz',
        'com.au',
        'co.at',
        'com.br',
        'co.in',
        'org.es',
        'net.es',
        'gob.es',
        'edu.es',
    ];

    public static function looksLikeDirectoryListing(string $domain): bool
    {
        $domain = strtolower(rtrim(trim($domain), '.'));
        if ($domain === '' || ! str_contains($domain, '.')) {
            return false;
        }

        $labels = explode('.', $domain);
        $parentLabelCount = self::parentLabelCount($labels);
        if (count($labels) <= $parentLabelCount) {
            return false;
        }

        $parent = implode('.', array_slice($labels, -$parentLabelCount));
        $sub = implode('.', array_slice($labels, 0, -$parentLabelCount));
        if ($sub === '' || $sub === 'www' || $sub === 'mail') {
            return false;
        }

        $parentLooksLikeDirectory = preg_match('/hotel|hostal|apart|resort|aloj|booking/i', $parent) === 1;
        $subLooksLikeSlug = str_contains($sub, '-') || strlen($sub) >= 16;

        return $parentLooksLikeDirectory || $subLooksLikeSlug;
    }

    /**
     * @param  list<string>  $labels
     */
    private static function parentLabelCount(array $labels): int
    {
        if (count($labels) < 2) {
            return count($labels);
        }

        $lastTwo = implode('.', array_slice($labels, -2));
        if (in_array($lastTwo, self::MULTI_PART_TLDS, true)) {
            return 3;
        }

        return 2;
    }
}
