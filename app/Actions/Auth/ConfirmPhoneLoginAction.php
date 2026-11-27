<?php

namespace App\Actions\Auth;

use App\Models\BackofficeLoginChallenge;

/**
 * De telefoon geeft de computer vrij. De telefoon zelf krijgt geen beheersessie.
 */
class ConfirmPhoneLoginAction
{
    /**
     * @return array{ok: bool, error?: string}
     */
    public function handle(string $token): array
    {
        $challenge = BackofficeLoginChallenge::findByPlainToken($token);
        if ($challenge === null || ! $challenge->isOpen()) {
            return ['ok' => false, 'error' => 'expired'];
        }

        if ($challenge->status !== BackofficeLoginChallenge::STATUS_AUTHENTICATED) {
            return ['ok' => false, 'error' => 'failed'];
        }

        $challenge->loadMissing('tenant');
        if (! $challenge->tenant?->allowsBackofficePhoneLogin()) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $challenge->update(['status' => BackofficeLoginChallenge::STATUS_APPROVED]);

        return ['ok' => true];
    }
}
