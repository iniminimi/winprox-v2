<?php

namespace App\Actions\Auth;

use App\Models\BackofficeLoginChallenge;
use App\Models\User;

/**
 * De computer die het ticket maakte, haalt de goedgekeurde gebruiker op.
 */
class ConsumeBackofficeLoginChallengeAction
{
    public function handle(string $sessionId): ?User
    {
        $challenge = BackofficeLoginChallenge::query()
            ->where('session_id', $sessionId)
            ->where('status', BackofficeLoginChallenge::STATUS_APPROVED)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($challenge === null) {
            return null;
        }

        $user = User::query()
            ->whereKey($challenge->user_id)
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            return null;
        }

        $challenge->update(['status' => BackofficeLoginChallenge::STATUS_CONSUMED]);

        return $user;
    }
}
