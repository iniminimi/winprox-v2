<?php

namespace App\Exceptions\Public;

use RuntimeException;

class PublicReportRateLimitExceededException extends RuntimeException
{
    public const REASON_COOLDOWN = 'cooldown';

    public const REASON_LIMIT = 'limit';

    public function __construct(
        public readonly int $retryAfterSeconds,
        public readonly string $reason = self::REASON_LIMIT,
        public readonly int $maxAttempts = 0,
    ) {
        parent::__construct('public_report_rate_limited');
    }
}
