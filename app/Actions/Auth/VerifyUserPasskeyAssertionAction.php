<?php

namespace App\Actions\Auth;

use App\Models\BackofficeLoginChallenge;
use App\Models\User;
use App\Models\UserPasskeyCredential;
use App\Support\Auth\UserPasskeyCeremony;
use InvalidArgumentException;

/**
 * De vingerafdruk op de telefoon meldt de computer aan. Geen wachtwoord op de gsm.
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

        $user = $this->userFromCredential($credentialJson);
        if (! $user->tenant?->allowsBackofficePhoneLogin()) {
            throw new InvalidArgumentException('disabled');
        }

        [, $updated] = UserPasskeyCeremony::verifyAssertion($user, $credentialJson, $options, $rpId, $origins);

        $user->passkeyCredential?->update([
            'sign_count' => $updated->counter,
            'last_used_at' => now(),
        ]);

        $challenge->update([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'status' => BackofficeLoginChallenge::STATUS_APPROVED,
        ]);
    }

    private function userFromCredential(string $credentialJson): User
    {
        $payload = json_decode($credentialJson, true);
        $credentialId = is_array($payload) ? (string) ($payload['id'] ?? $payload['rawId'] ?? '') : '';
        if ($credentialId === '') {
            throw new InvalidArgumentException('unknown_credential');
        }

        $stored = UserPasskeyCredential::query()->where('credential_id', $credentialId)->first();
        $user = $stored?->user;
        if (! $stored instanceof UserPasskeyCredential || ! $user instanceof User || ! $user->is_active) {
            throw new InvalidArgumentException('unknown_credential');
        }

        $user->setRelation('passkeyCredential', $stored);

        return $user;
    }
}
