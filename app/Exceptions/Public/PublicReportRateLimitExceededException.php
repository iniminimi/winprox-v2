<?php

namespace App\Exceptions\Public;

use RuntimeException;

class PublicReportRateLimitExceededException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('public_report_rate_limited');
    }
}
