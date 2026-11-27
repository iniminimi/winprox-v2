<?php

namespace App\Actions\Auth;

use App\Models\BackofficeLoginChallenge;
use App\Models\User;

/**
 * Gegevens voor de telefoonpagina. Geen beheersessie.
 *
 * @return array<string, mixed>|null
 */
class ShowPhoneLoginChallengeAction
{
    public function handle(string $token): ?array
    {
        $challenge = BackofficeLoginChallenge::findByPlainToken($token);
        if ($challenge === null || ! $challenge->isOpen()) {
            return null;
        }

        $challenge->loadMissing(['user.passkeyCredential', 'tenant']);
        $user = $challenge->user;
        if ($user instanceof User && ! $challenge->tenant?->allowsBackofficePhoneLogin()) {
            return null;
        }

        $hasPasskey = $user instanceof User && $user->passkeyCredential !== null;

        return [
            'code' => $challenge->confirmation_code,
            'computer' => $challenge->computer_label,
            'organization' => $user instanceof User ? (string) $challenge->tenant?->name : '',
            'status' => $challenge->status,
            'has_passkey' => $hasPasskey,
            'can_register' => $user instanceof User && (! $hasPasskey || (bool) $user->phone_reregistration_open),
        ];
    }
}
