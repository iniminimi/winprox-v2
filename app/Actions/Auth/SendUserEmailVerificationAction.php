<?php

namespace App\Actions\Auth;

use App\Actions\Marketing\AssessPromoCampaignEmailAction;
use App\Enums\PromoEmailPreflightReason;
use App\Mail\VerifyUserEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Verstuurt de verificatiemail met een korte, tijdelijke token-link.
 */
class SendUserEmailVerificationAction
{
    private const MAX_PER_WINDOW = 3;

    private const WINDOW_SECONDS = 600;

    public function __construct(
        private AssessPromoCampaignEmailAction $assessEmail,
    ) {}

    public function assertRecipientDeliverable(string $email): string
    {
        $assessment = $this->assessEmail->handle($email);
        if (! $assessment->hasEmail) {
            throw ValidationException::withMessages([
                'email' => [__('auth.errors.email_required')],
            ]);
        }

        if ($assessment->reason === PromoEmailPreflightReason::PreviouslyBounced) {
            throw ValidationException::withMessages([
                'email' => [__('auth.errors.email_undeliverable')],
            ]);
        }

        if ($assessment->normalizedEmail === null) {
            throw ValidationException::withMessages([
                'email' => [__('auth.errors.email_invalid')],
            ]);
        }

        return $assessment->normalizedEmail;
    }

    /**
     * @return array{sent: bool, retry_after: int}
     */
    public function handle(User $user): array
    {
        if ($user->hasVerifiedEmail()) {
            return ['sent' => false, 'retry_after' => 0];
        }

        $email = $this->assertRecipientDeliverable((string) $user->email);

        $key = 'verify-email:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_WINDOW)) {
            return ['sent' => false, 'retry_after' => max(1, RateLimiter::availableIn($key))];
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        $minutes = max(1, (int) config('auth.verification.expire', 60));
        $token = $this->issueToken($user, $minutes);

        Mail::to($email)->send(new VerifyUserEmailMail($user, $token, $minutes));

        return ['sent' => true, 'retry_after' => 0];
    }

    private function issueToken(User $user, int $minutes): string
    {
        do {
            $token = Str::lower(Str::random(48));
            $hash = hash('sha256', $token);
        } while (User::query()->where('email_verify_token', $hash)->exists());

        $user->forceFill([
            'email_verify_token' => $hash,
            'email_verify_expires_at' => now()->addMinutes($minutes),
        ])->save();

        return $token;
    }
}
