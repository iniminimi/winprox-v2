<?php

namespace App\Actions\Auth;

use App\Enums\BackofficeLoginThrottleStep;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Begrenst mislukte beheerlogins (e-mail + wachtwoord).
 *
 * Drie tellers, zodat één gedeeld kantoor-IP niet meteen iedereen uitsluit
 * en een wisselend IP één account niet onbeperkt kan proberen:
 * - hetzelfde e-mailadres én IP
 * - hetzelfde e-mailadres, ongeacht IP
 * - hetzelfde IP, ongeacht e-mailadres
 *
 * Drempels niet overnemen naar de publieke technische fiche.
 */
class ThrottleBackofficeLoginAction
{
    public const PAIR_MAX_ATTEMPTS = 5;

    public const PAIR_DECAY_SECONDS = 300;

    public const EMAIL_MAX_ATTEMPTS = 10;

    public const EMAIL_DECAY_SECONDS = 900;

    public const IP_MAX_ATTEMPTS = 100;

    public const IP_DECAY_SECONDS = 900;

    public function __construct(private AuditRecorder $audit) {}

    /**
     * Check: seconden tot een nieuwe poging mag, of null.
     * Failure: telt een mislukte poging en geeft diezelfde wachttijd terug.
     * Success: wist de tellers van dit e-mailadres (niet de IP-teller).
     */
    public function handle(string $email, string $ip, BackofficeLoginThrottleStep $step): ?int
    {
        $email = Str::lower(trim($email));
        $ip = trim($ip) !== '' ? trim($ip) : 'unknown';

        return match ($step) {
            BackofficeLoginThrottleStep::Check => $this->retryAfter($email, $ip),
            BackofficeLoginThrottleStep::Failure => $this->recordFailure($email, $ip),
            BackofficeLoginThrottleStep::Success => $this->clearEmail($email, $ip),
        };
    }

    private function recordFailure(string $email, string $ip): ?int
    {
        $wasBlocked = $this->retryAfter($email, $ip) !== null;

        RateLimiter::hit($this->pairKey($email, $ip), self::PAIR_DECAY_SECONDS);
        RateLimiter::hit($this->emailKey($email), self::EMAIL_DECAY_SECONDS);
        RateLimiter::hit($this->ipKey($ip), self::IP_DECAY_SECONDS);

        $retryAfter = $this->retryAfter($email, $ip);
        if (! $wasBlocked && $retryAfter !== null) {
            $this->auditLock($email, $ip, $retryAfter);
        }

        return $retryAfter;
    }

    private function clearEmail(string $email, string $ip): null
    {
        RateLimiter::clear($this->pairKey($email, $ip));
        RateLimiter::clear($this->emailKey($email));

        return null;
    }

    private function retryAfter(string $email, string $ip): ?int
    {
        $wait = [];

        if (RateLimiter::tooManyAttempts($this->pairKey($email, $ip), self::PAIR_MAX_ATTEMPTS)) {
            $wait[] = RateLimiter::availableIn($this->pairKey($email, $ip));
        }

        if (RateLimiter::tooManyAttempts($this->emailKey($email), self::EMAIL_MAX_ATTEMPTS)) {
            $wait[] = RateLimiter::availableIn($this->emailKey($email));
        }

        if (RateLimiter::tooManyAttempts($this->ipKey($ip), self::IP_MAX_ATTEMPTS)) {
            $wait[] = RateLimiter::availableIn($this->ipKey($ip));
        }

        if ($wait === []) {
            return null;
        }

        return max(1, max($wait));
    }

    private function auditLock(string $email, string $ip, int $retryAfter): void
    {
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user === null || $user->tenant_id === null) {
            return;
        }

        $this->audit->record(
            userId: $user->id,
            tenantId: (int) $user->tenant_id,
            action: 'auth.login_throttled',
            modelType: User::class,
            modelId: $user->id,
            payload: [
                'ip' => $ip,
                'retry_after_seconds' => $retryAfter,
            ],
        );
    }

    private function pairKey(string $email, string $ip): string
    {
        return 'backoffice-login:pair:'.hash('sha256', $email.'|'.$ip);
    }

    private function emailKey(string $email): string
    {
        return 'backoffice-login:email:'.hash('sha256', $email);
    }

    private function ipKey(string $ip): string
    {
        return 'backoffice-login:ip:'.hash('sha256', $ip);
    }
}
