<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Hard throttle for Cloud86 shared SMTP (~250 outgoing messages / hour / plan).
 * Queue delays alone are not enough when workers process available jobs back-to-back.
 */
final class PromoSmtpThrottle
{
    public static function cacheKey(): string
    {
        return 'winprox-promo-smtp';
    }

    public static function intervalSeconds(): int
    {
        return max(1, (int) config('winprox.promo_campaign_email_min_interval_seconds', 20));
    }

    /**
     * @return int|null Seconds to wait before retrying, or null when a send is allowed now.
     */
    public static function secondsUntilAvailable(): ?int
    {
        if (! RateLimiter::tooManyAttempts(self::cacheKey(), 1)) {
            return null;
        }

        return max(1, RateLimiter::availableIn(self::cacheKey()));
    }

    /**
     * Reserve a send slot. Returns false when another worker won the race or the slot is taken.
     */
    public static function tryAcquire(): bool
    {
        $lock = Cache::lock('winprox-promo-smtp-lock', 10);

        if (! $lock->get()) {
            return false;
        }

        try {
            if (RateLimiter::tooManyAttempts(self::cacheKey(), 1)) {
                return false;
            }

            RateLimiter::hit(self::cacheKey(), self::intervalSeconds());

            return true;
        } finally {
            $lock->release();
        }
    }
}
