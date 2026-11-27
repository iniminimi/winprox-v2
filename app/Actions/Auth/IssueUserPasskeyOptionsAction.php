<?php

namespace App\Actions\Auth;

use App\Models\BackofficeLoginChallenge;
use App\Models\User;
use App\Support\Auth\UserPasskeyCeremony;
use InvalidArgumentException;

/**
 * WebAuthn-opties voor het open ticket. De telefoon is nog niet aangemeld in het beheer.
 */
class IssueUserPasskeyOptionsAction
{
    /**
     * @param  list<string>  $origins
     * @return array<string, mixed>
     */
    public function handle(string $token, string $rpId, bool $registration): array
    {
        $challenge = $this->openChallenge($token);

        if ($registration) {
            $user = $this->boundUser($challenge);
            if ($challenge->status !== BackofficeLoginChallenge::STATUS_AUTHENTICATED) {
                throw new InvalidArgumentException('failed');
            }
            if ($user->passkeyCredential !== null && ! $user->phone_reregistration_open) {
                throw new InvalidArgumentException('failed');
            }
            $options = UserPasskeyCeremony::registrationOptions($user, $rpId);
            $challenge->update(['registration_options' => json_encode($options)]);

            return $options;
        }

        if ($challenge->status !== BackofficeLoginChallenge::STATUS_PENDING) {
            throw new InvalidArgumentException('failed');
        }

        $user = $challenge->user;
        $user?->loadMissing('passkeyCredential');
        $options = $user instanceof User && $user->passkeyCredential !== null
            ? UserPasskeyCeremony::assertionOptions($user, $rpId)
            : UserPasskeyCeremony::discoverableAssertionOptions($rpId);
        $challenge->update(['assertion_options' => json_encode($options)]);

        return $options;
    }

    private function boundUser(BackofficeLoginChallenge $challenge): User
    {
        $challenge->loadMissing(['user.passkeyCredential', 'tenant']);
        $user = $challenge->user;
        if (! $user instanceof User || ! $challenge->tenant?->allowsBackofficePhoneLogin()) {
            throw new InvalidArgumentException($user instanceof User ? 'disabled' : 'expired');
        }

        return $user;
    }

    private function openChallenge(string $token): BackofficeLoginChallenge
    {
        $challenge = BackofficeLoginChallenge::findByPlainToken($token);
        if ($challenge === null || ! $challenge->isOpen()) {
            throw new InvalidArgumentException('expired');
        }

        return $challenge;
    }
}
