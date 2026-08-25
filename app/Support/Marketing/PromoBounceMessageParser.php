<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Enums\PromoBounceKind;
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
     * Domains that appear in bounce envelopes / Message-IDs, never real recipients.
     *
     * @var list<string>
     */
    private const REJECTED_DOMAIN_SUFFIXES = [
        'winprox.app',
        'cloud86-host.io',
        'localhost',
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

    /**
     * Combine bounce headers, bodies and DSN parts so Final-Recipient is not missed.
     */
    public static function haystackFromParts(
        string $headers = '',
        string $textBody = '',
        string $htmlBody = '',
        string $rawBody = '',
        string $attachmentBodies = '',
    ): string {
        return implode("\n", array_values(array_filter(
            [$headers, $textBody, $htmlBody, $attachmentBodies, $rawBody],
            fn (string $part): bool => trim($part) !== '',
        )));
    }

    public static function looksLikeBounce(string $subject, string $from): bool
    {
        $subjectLower = mb_strtolower($subject);
        $fromLower = mb_strtolower($from);

        $subjectHints = [
            'undelivered mail returned to sender',
            'delivery status notification',
            'mail delivery failed',
            'returning message to sender',
            'returned mail',
            'returned to sender',
            'failure notice',
            'delivery failure',
            'delivery notification',
            'undeliverable',
            'onbestelbaar',
            'niet bezorgd',
            'niet afgeleverd',
            'non remis',
            'unzustellbar',
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

    public static function classify(string $haystack): PromoBounceKind
    {
        $text = self::searchableText($haystack);

        if (self::matchesAny($text, [
            'mailbox full',
            'mailbox is full',
            'over quota',
            'quota exceeded',
            'user is overquota',
            'user is over quota',
            'insufficient system storage',
            'status: 5.2.2',
            '5.2.2',
        ])) {
            return PromoBounceKind::MailboxFull;
        }

        if (self::matchesAny($text, [
            'host in blacklist',
            'listed in blacklist',
            'blacklisted',
            'blocked using',
            'spamhaus',
            'barracuda',
            'dnsbl',
            ' listed by ',
            'blocked by',
            'client host rejected',
        ]) || (str_contains($text, 'blacklist') && str_contains($text, 'host'))) {
            return PromoBounceKind::Blacklist;
        }

        if (self::matchesAny($text, [
            'user unknown',
            'unknown user',
            'no such user',
            'user not found',
            'mailbox not found',
            'unknown mailbox',
            'no mailbox',
            'does not exist',
            'invalid recipient',
            'recipient rejected',
            'recipient address rejected',
            'status: 5.1.1',
            '5.1.1',
            '550 5.1.1',
        ])) {
            return PromoBounceKind::Unknown;
        }

        return PromoBounceKind::Other;
    }

    public static function storageReason(string $haystack): string
    {
        $kind = self::classify($haystack);
        $snippet = self::diagnosticSnippet($haystack);

        return trim($kind->storagePrefix().' '.($snippet !== '' ? $snippet : $kind->value));
    }

    /**
     * @param  list<string>  $needles
     */
    private static function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function diagnosticSnippet(string $haystack): string
    {
        $slice = self::searchableText($haystack);

        try {
            if (preg_match('/^Diagnostic-Code:\s*(.+)$/mi', $slice, $matches) === 1) {
                return mb_substr(trim((string) $matches[1]), 0, 400);
            }

            if (preg_match('/550[^\r\n]{0,200}/i', $slice, $matches) === 1) {
                return mb_substr(trim((string) $matches[0]), 0, 400);
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    /**
     * ASCII-safe snippet so bounce MIME (invalid UTF-8 / huge nested rfc822) cannot
     * blow up mb_strtolower or PCRE during IMAP processing.
     */
    private static function searchableText(string $haystack): string
    {
        $parts = [substr($haystack, 0, 20_000)];
        if (strlen($haystack) > 20_000) {
            $parts[] = substr($haystack, -20_000);
        }

        try {
            if (preg_match('/Diagnostic-Code:[^\r\n]{0,400}/i', $haystack, $matches) === 1) {
                $parts[] = $matches[0];
            }
        } catch (\Throwable) {
            // keep head/tail only
        }

        return strtolower(implode("\n", $parts));
    }

    /**
     * Whether an address is a plausible real recipient (not Message-ID / system mail).
     */
    public static function isPlausibleRecipientEmail(string $email): bool
    {
        return self::normalizeCandidate($email) !== null;
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
     * Last-resort extraction near failure keywords — never scrape Message-ID headers.
     *
     * @return list<string>
     */
    private static function fallbackBodyEmails(string $haystack): array
    {
        $emails = [];
        $patterns = [
            '/(?:RCPT\s+TO|Failed\s+recipient|Intended\s+recipient|Recipient\s+address)\s*[:=]?\s*<?([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})>?/i',
            '/The\s+following\s+address(?:es)?[^\n]{0,80}?([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i',
            '/(?:to|voor)\s*<?([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})>?\s+(?:failed|could not|was not|kon niet)/i',
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

    private static function normalizeCandidate(string $raw): ?string
    {
        $raw = trim($raw, " \t\n\r\0\x0B\"'<>");
        $raw = preg_replace('/^rfc822\s*;\s*/i', '', $raw) ?? $raw;
        $raw = trim($raw);

        if ($raw === '' || filter_var($raw, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $email = EmailUnsubscribe::normalizeEmail($raw);
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return null;
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);

        if ($local === '' || $domain === '') {
            return null;
        }

        if (in_array($local, self::SYSTEM_LOCAL_PARTS, true)) {
            return null;
        }

        if (self::looksLikeMessageIdLocalPart($local)) {
            return null;
        }

        if (self::isRejectedDomain($domain)) {
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

    private static function looksLikeMessageIdLocalPart(string $local): bool
    {
        // Classic Message-ID: 178430534480.3659332.10843687058335425167
        if (preg_match('/^\d+(?:\.\d+){1,}$/', $local) === 1) {
            return true;
        }

        // Hash / MD5-style ids used as Message-ID local parts
        if (preg_match('/^[a-f0-9]{16,}$/i', $local) === 1) {
            return true;
        }

        return false;
    }

    private static function isRejectedDomain(string $domain): bool
    {
        $domain = strtolower($domain);

        foreach (self::REJECTED_DOMAIN_SUFFIXES as $suffix) {
            if ($domain === $suffix || str_ends_with($domain, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
