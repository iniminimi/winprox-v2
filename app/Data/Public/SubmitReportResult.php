<?php

declare(strict_types=1);

namespace App\Data\Public;

use App\Models\Issue;

final readonly class SubmitReportResult
{
    public function __construct(
        public ?Issue $issue,
        public bool $awaitingEmailVerification,
    ) {}
}
