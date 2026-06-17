<?php

namespace App\Actions\Public;

use App\Exceptions\Public\PublicReportRateLimitExceededException;
use Illuminate\Support\Facades\RateLimiter;

class AssertPublicReportRateLimitAction
{
    public function handle(int $tenantId, int $unitId, string $clientIp): void
    {
        if (! config('portal.public_report_rate_limit.enabled', true)) {
            return;
        }

        $clientIp = trim($clientIp);
        if ($clientIp === '') {
            return;
        }

        $cooldownDecay = (int) config('portal.public_report_rate_limit.cooldown.decay_seconds', 180);
        if ($cooldownDecay > 0) {
            $cooldownKey = $this->cooldownKey($tenantId, $unitId, $clientIp);
            if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
                throw new PublicReportRateLimitExceededException(
                    RateLimiter::availableIn($cooldownKey),
                    PublicReportRateLimitExceededException::REASON_COOLDOWN,
                );
            }
        }

        $unitKey = $this->unitKey($tenantId, $unitId, $clientIp);
        $unitLimit = (int) config('portal.public_report_rate_limit.per_unit.max_attempts', 5);

        if (RateLimiter::tooManyAttempts($unitKey, $unitLimit)) {
            throw new PublicReportRateLimitExceededException(
                RateLimiter::availableIn($unitKey),
                PublicReportRateLimitExceededException::REASON_LIMIT,
                $unitLimit,
            );
        }

        $tenantKey = $this->tenantKey($tenantId, $clientIp);
        $tenantLimit = (int) config('portal.public_report_rate_limit.per_tenant.max_attempts', 20);

        if (RateLimiter::tooManyAttempts($tenantKey, $tenantLimit)) {
            throw new PublicReportRateLimitExceededException(
                RateLimiter::availableIn($tenantKey),
                PublicReportRateLimitExceededException::REASON_LIMIT,
                $tenantLimit,
            );
        }
    }

    public function cooldownKey(int $tenantId, int $unitId, string $clientIp): string
    {
        return 'public-report:cooldown:'.$tenantId.':'.$unitId.':'.$clientIp;
    }

    public function unitKey(int $tenantId, int $unitId, string $clientIp): string
    {
        return 'public-report:unit:'.$tenantId.':'.$unitId.':'.$clientIp;
    }

    public function tenantKey(int $tenantId, string $clientIp): string
    {
        return 'public-report:tenant:'.$tenantId.':'.$clientIp;
    }
}
