<?php

declare(strict_types=1);

namespace App\Support\Marketing;

/**
 * Herkent geautomatiseerde link-checks (mailgateways, security scanners) zodat die
 * geen promo-bezoeken loggen.
 */
final class PromoVisitScannerDetector
{
    /**
     * Case-insensitive substrings in User-Agent (e-mailgateways en HTTP-clients).
     *
     * @var list<string>
     */
    private const USER_AGENT_NEEDLES = [
        'safelinks',
        'proofpoint',
        'urldefense',
        'mimecast',
        'barracuda',
        'ironport',
        'messagelabs',
        'symantec',
        'broadcom',
        'fireeye',
        'trend micro',
        'forcepoint',
        'sophos',
        'mcafee',
        'zscaler',
        'checkpoint',
        'bitdefender',
        'eset',
        'kaspersky',
        'mailmarshal',
        'spamassassin',
        'urlscan',
        'google-safety',
        'headlesschrome',
        'phantomjs',
        'selenium',
        'curl/',
        'wget/',
        'python-requests',
        'python-urllib',
        'go-http-client',
        'apache-httpclient',
        'okhttp',
        'libwww-perl',
        'java/',
        'node-fetch',
        'axios/',
        'scrapy',
    ];

    public static function isAutomatedFetch(?string $userAgent): bool
    {
        if ($userAgent === null) {
            return false;
        }

        $userAgent = trim($userAgent);
        if ($userAgent === '') {
            return false;
        }

        $normalized = strtolower($userAgent);

        foreach (self::USER_AGENT_NEEDLES as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
