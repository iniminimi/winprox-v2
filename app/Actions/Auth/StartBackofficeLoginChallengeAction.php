<?php

namespace App\Actions\Auth;

use App\Models\BackofficeLoginChallenge;
use App\Support\Auth\ComputerLabel;
use Illuminate\Support\Facades\Schema;

/**
 * Maakt of hergebruikt het QR-ticket van deze computer. Het adres volgt op de telefoon.
 */
class StartBackofficeLoginChallengeAction
{
    private const TTL_SECONDS = 120;

    /**
     * @return array{token: string, code: string, expires_at: string}|null
     */
    public function handle(string $sessionId, string $userAgent, ?string $currentToken = null): ?array
    {
        if (! Schema::hasTable('backoffice_login_challenges')) {
            return null;
        }

        if ($currentToken !== null && $currentToken !== '') {
            $existing = BackofficeLoginChallenge::findByPlainToken($currentToken);
            if ($existing !== null
                && $existing->isOpen()
                && $existing->session_id === $sessionId
            ) {
                return [
                    'token' => $currentToken,
                    'code' => $existing->confirmation_code,
                    'expires_at' => $existing->expires_at->toIso8601String(),
                ];
            }
        }

        $this->dropPending($sessionId);

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = BackofficeLoginChallenge::query()->create([
            'token_hash' => hash('sha256', $token),
            'confirmation_code' => str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
            'session_id' => $sessionId,
            'user_id' => null,
            'tenant_id' => null,
            'computer_label' => ComputerLabel::fromUserAgent($userAgent),
            'status' => BackofficeLoginChallenge::STATUS_PENDING,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        return [
            'token' => $token,
            'code' => $challenge->confirmation_code,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ];
    }

    private function dropPending(string $sessionId): void
    {
        if (! Schema::hasTable('backoffice_login_challenges')) {
            return;
        }

        BackofficeLoginChallenge::query()
            ->where('session_id', $sessionId)
            ->where('status', BackofficeLoginChallenge::STATUS_PENDING)
            ->delete();
    }
}
