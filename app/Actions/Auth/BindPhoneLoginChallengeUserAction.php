<?php

namespace App\Actions\Auth;

use App\Models\BackofficeLoginChallenge;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Koppelt het open QR-ticket aan het e-mailadres op de telefoon, zodat biometrie kan.
 */
class BindPhoneLoginChallengeUserAction
{
    public function handle(string $token, string $email): void
    {
        $challenge = BackofficeLoginChallenge::findByPlainToken($token);
        if ($challenge === null || ! $challenge->isOpen() || $challenge->status !== BackofficeLoginChallenge::STATUS_PENDING) {
            return;
        }

        $email = Str::lower(trim($email));
        $user = $email === '' ? null : User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->whereNotNull('tenant_id')
            ->first();

        if (! $user instanceof User || ! $user->tenant?->allowsBackofficePhoneLogin()) {
            if ($challenge->user_id !== null) {
                $challenge->update(['user_id' => null, 'tenant_id' => null]);
            }

            return;
        }

        if ((int) $challenge->user_id === (int) $user->id && (int) $challenge->tenant_id === (int) $user->tenant_id) {
            return;
        }

        $challenge->update([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }
}
