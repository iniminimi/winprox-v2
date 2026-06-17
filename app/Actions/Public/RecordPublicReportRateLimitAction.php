<?php

namespace App\Actions\Public;

use Illuminate\Support\Facades\RateLimiter;

class RecordPublicReportRateLimitAction
{
    public function __construct(
        private AssertPublicReportRateLimitAction $keys,
    ) {}

    public function handle(int $tenantId, int $unitId, string $clientIp): void
    {
        if (! config('portal.public_report_rate_limit.enabled', true)) {
            return;
        }

        $clientIp = trim($clientIp);
        if ($clientIp === '') {
            return;
        }

        $unitDecay = (int) config('portal.public_report_rate_limit.per_unit.decay_seconds', 900);
        $tenantDecay = (int) config('portal.public_report_rate_limit.per_tenant.decay_seconds', 3600);

        RateLimiter::hit($this->keys->unitKey($tenantId, $unitId, $clientIp), $unitDecay);
        RateLimiter::hit($this->keys->tenantKey($tenantId, $clientIp), $tenantDecay);
    }
}
