<?php

declare(strict_types=1);

namespace App\Data\Marketing;

use App\Enums\PromoEmailPreflightReason;

final readonly class PromoCampaignSkippedEmailData
{
    public function __construct(
        public string $name,
        public string $email,
        public PromoEmailPreflightReason $reason,
    ) {}
}
