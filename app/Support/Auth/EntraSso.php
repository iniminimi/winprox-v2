<?php

namespace App\Support\Auth;

use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * HTTP-hulp voor Microsoft Entra OIDC. Geen DB, geen sessie.
 */
final class EntraSso
{
    public static function enabled(): bool
    {
        return filled(config('services.azure.client_id'))
            && filled(config('services.azure.client_secret'));
    }

    /**
     * E-mailkandidaten uit het Microsoft-profiel (mail vóór UPN).
     *
     * @return list<string>
     */
    public static function candidateEmails(SocialiteUser $user): array
    {
        $raw = [];
        if (method_exists($user, 'getRaw')) {
            $raw = $user->getRaw();
        }
        if ($raw === [] && isset($user->user) && is_array($user->user)) {
            $raw = $user->user;
        }

        $candidates = [
            is_array($raw) ? ($raw['mail'] ?? null) : null,
            $user->getEmail(),
            is_array($raw) ? ($raw['userPrincipalName'] ?? null) : null,
            is_array($raw) ? ($raw['preferred_username'] ?? null) : null,
        ];

        $emails = [];
        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $normalized = strtolower(trim($candidate));
            if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $emails[$normalized] = true;
        }

        return array_keys($emails);
    }
}
