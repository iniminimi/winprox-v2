<?php

namespace App\Actions\Auth;

use App\Models\BackofficeLoginChallenge;
use App\Support\Auth\UserPasskeyCeremony;
use InvalidArgumentException;

/**
 * Controleert de handtekening van de telefoon. De computer wacht nog op bevestigen.
 */
class VerifyUserPasskeyAssertionAction
{
    /**
     * @param  list<string>  $origins
     */
    public function handle(string $token, string $credentialJson, string $rpId, array $origins): void
    {
        $challenge = BackofficeLoginChallenge::findByPlainToken($token);
        if ($challenge === null || ! $challenge->isOpen() || $challenge->assertion_options === null) {
            throw new InvalidArgumentException('expired');
        }

        $options = $challenge->assertion_options;
        $challenge->update(['assertion_options' => null]);
        $challenge->loadMissing('user.passkeyCredential');

        $user = $challenge->user;
        if ($user === null) {
            throw new InvalidArgumentException('expired');
        }

        [, $updated] = UserPasskeyCeremony::verifyAssertion($user, $credentialJson, $options, $rpId, $origins);

        $user->passkeyCredential?->update([
            'sign_count' => $updated->counter,
            'last_used_at' => now(),
        ]);

        $challenge->update(['status' => BackofficeLoginChallenge::STATUS_AUTHENTICATED]);
    }
}
