<?php

namespace App\Actions\Auth;

use App\Enums\BackofficeLoginThrottleStep;
use App\Models\BackofficeLoginChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Controleert e-mail en wachtwoord op de telefoon en meldt de computer nog niet aan.
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

        $typed = Str::lower(trim($email));
        $user = $challenge->user;
        if (! $user instanceof User && $typed !== '') {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$typed])
                ->where('is_active', true)
                ->whereNotNull('tenant_id')
                ->first();
        }

        if (! $user instanceof User) {
            if ($typed !== '') {
                $this->throttle->handle($typed, $ip, BackofficeLoginThrottleStep::Failure);
            }

            return ['ok' => false, 'error' => 'failed'];
        }

        if (! $user->tenant?->allowsBackofficePhoneLogin()) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $seconds = $this->throttle->handle($user->email, $ip, BackofficeLoginThrottleStep::Check);
        if ($seconds !== null) {
            return ['ok' => false, 'error' => 'throttle'];
        }

        $emailMatches = $typed === Str::lower($user->email);
        if (! $emailMatches || ! Hash::check($password, $user->password)) {
            $this->throttle->handle($user->email, $ip, BackofficeLoginThrottleStep::Failure);

            return ['ok' => false, 'error' => 'failed'];
        }

        $this->throttle->handle($user->email, $ip, BackofficeLoginThrottleStep::Success);
        if ($challenge->status === BackofficeLoginChallenge::STATUS_PENDING) {
            $challenge->update([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'status' => BackofficeLoginChallenge::STATUS_AUTHENTICATED,
            ]);
        }

        return ['ok' => true];
    }

    private function openChallenge(string $token): ?BackofficeLoginChallenge
    {
        $challenge = BackofficeLoginChallenge::findByPlainToken($token);
        if ($challenge === null || ! $challenge->isOpen()) {
            return null;
        }

        if (! in_array($challenge->status, [
            BackofficeLoginChallenge::STATUS_PENDING,
            BackofficeLoginChallenge::STATUS_AUTHENTICATED,
        ], true)) {
            return null;
        }

        $challenge->loadMissing(['user', 'tenant']);

        return $challenge;
    }
}
