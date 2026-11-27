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
        $challenge = $this->challenge($token);
        $user = $challenge->user;
        if (! $user instanceof User) {
            throw new InvalidArgumentException('expired');
        }

        $user->loadMissing('passkeyCredential');

        if ($registration) {
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

        if ($challenge->status !== BackofficeLoginChallenge::STATUS_PENDING || $user->passkeyCredential === null) {
            throw new InvalidArgumentException('failed');
        }

        $options = UserPasskeyCeremony::assertionOptions($user, $rpId);
        $challenge->update(['assertion_options' => json_encode($options)]);

        return $options;
    }

    private function challenge(string $token): BackofficeLoginChallenge
    {
        $challenge = BackofficeLoginChallenge::findByPlainToken($token);
        if ($challenge === null || ! $challenge->isOpen()) {
            throw new InvalidArgumentException('expired');
        }

        $challenge->loadMissing(['user', 'tenant']);
        if (! $challenge->tenant?->allowsBackofficePhoneLogin()) {
            throw new InvalidArgumentException('disabled');
        }

        return $challenge;
    }
}
