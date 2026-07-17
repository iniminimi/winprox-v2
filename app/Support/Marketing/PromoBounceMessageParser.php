<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Models\EmailUnsubscribe;

/**
 * Extracts failed recipient addresses from SMTP bounce / DSN messages.
 */
final class PromoBounceMessageParser
{
    private const SYSTEM_LOCAL_PARTS = [
        'mailer-daemon',
        'postmaster',
        'mail-daemon',
        'noreply',
        'no-reply',
    ];

    /**
     * @return list<string> Normalized email addresses
     */
    public static function extractRecipientEmails(string $subject, string $body): array
    {
        $haystack = $subject."\n".$body;
        $found = [];

        foreach (self::dsnFieldEmails($haystack) as $email) {
            $found[$email] = true;
        }

        if ($found === []) {
            foreach (self::fallbackBodyEmails($haystack) as $email) {
                $found[$email] = true;
            }
        }

        return array_keys($found);
    }

    public static function looksLikeBounce(string $subject, string $from): bool
    {
        $subjectLower = mb_strtolower($subject);
        $fromLower = mb_strtolower($from);

        $subjectHints = [
            'undelivered mail returned to sender',
            'delivery status notification',
            'mail delivery failed',
            'returned mail',
            'failure notice',
            'delivery failure',
            'undeliverable',
            'niet bezorgd',
            'non remis',
        ];

        foreach ($subjectHints as $hint) {
            if (str_contains($subjectLower, $hint)) {
                return true;
            }
        }

        foreach (['mailer-daemon', 'postmaster', 'mail delivery'] as $hint) {
            if (str_contains($fromLower, $hint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function dsnFieldEmails(string $haystack): array
    {
        $emails = [];
        $patterns = [
            '/Final-Recipient:\s*(?:rfc822\s*;\s*)?([^\s<>]+)/i',
            '/Original-Recipient:\s*(?:rfc822\s*;\s*)?([^\s<>]+)/i',
            '/X-Failed-Recipients:\s*([^\s,;<>]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $haystack, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $raw) {
                $email = self::normalizeCandidate((string) $raw);
                if ($email !== null) {
                    $emails[] = $email;
                }
            }
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    private static function fallbackBodyEmails(string $haystack): array
    {
        $emails = [];

        if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $haystack, $matches) === false) {
            return [];
        }

        foreach ($matches[0] as $raw) {
            $email = self::normalizeCandidate((string) $raw);
            if ($email !== null) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    private static function normalizeCandidate(string $raw): ?string
    {
        $raw = trim($raw, " \t\n\r\0\x0B\"'<>");
        $raw = preg_replace('/^rfc822\s*;\s*/i', '', $raw) ?? $raw;
        $raw = trim($raw);

        if ($raw === '' || filter_var($raw, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $email = EmailUnsubscribe::normalizeEmail($raw);
        $local = strstr($email, '@', true);
        if ($local === false) {
            return null;
        }

        if (in_array($local, self::SYSTEM_LOCAL_PARTS, true)) {
            return null;
        }

        $ownAddresses = [
            EmailUnsubscribe::normalizeEmail((string) config('winprox.municipal_promo_email_from.address')),
            EmailUnsubscribe::normalizeEmail((string) config('mail.from.address', 'info@winprox.app')),
            EmailUnsubscribe::normalizeEmail((string) config('winprox.helpdesk_email', 'helpdesk@winprox.app')),
        ];

        if (in_array($email, $ownAddresses, true)) {
            return null;
        }

        return $email;
    }
}
