<?php

declare(strict_types=1);

namespace App\Data\Marketing;

use App\Enums\PromoEmailPreflightReason;

final readonly class PromoEmailAssessmentData
{
    public function __construct(
        public bool $hasEmail,
        public bool $accepted,
        public ?string $normalizedEmail,
        public ?PromoEmailPreflightReason $reason,
    ) {}
}
