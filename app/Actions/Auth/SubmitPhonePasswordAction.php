<?php

namespace App\Actions\Auth;

use App\Enums\BackofficeLoginThrottleStep;
use App\Models\BackofficeLoginChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Controleert het wachtwoord op de telefoon en meldt de computer nog niet aan.
 */
class SubmitPhonePasswordAction
{
    public function __construct(private ThrottleBackofficeLoginAction $throttle) {}

    /**
     * @return array{ok: bool, error?: string}
     */
    public function handle(string $token, string $email, string $password, string $ip): array
    {
        $challenge = $this->openChallenge($token);
        if ($challenge === null) {
            return ['ok' => false, 'error' => 'expired'];
        }

        $user = $challenge->user;
        if (! $user instanceof User || ! $challenge->tenant?->allowsBackofficePhoneLogin()) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $seconds = $this->throttle->handle($user->email, $ip, BackofficeLoginThrottleStep::Check);
        if ($seconds !== null) {
            return ['ok' => false, 'error' => 'throttle'];
        }

        $emailMatches = Str::lower(trim($email)) === Str::lower($user->email);
        if (! $emailMatches || ! Hash::check($password, $user->password)) {
            $this->throttle->handle($user->email, $ip, BackofficeLoginThrottleStep::Failure);

            return ['ok' => false, 'error' => 'failed'];
        }

        $this->throttle->handle($user->email, $ip, BackofficeLoginThrottleStep::Success);
        $challenge->update(['status' => BackofficeLoginChallenge::STATUS_AUTHENTICATED]);

        return ['ok' => true];
    }

    private function openChallenge(string $token): ?BackofficeLoginChallenge
    {
        $challenge = BackofficeLoginChallenge::findByPlainToken($token);
        if ($challenge === null || ! $challenge->isOpen()) {
            return null;
        }

        if ($challenge->status !== BackofficeLoginChallenge::STATUS_PENDING) {
            return null;
        }

        $challenge->loadMissing(['user', 'tenant']);

        return $challenge;
    }
}
