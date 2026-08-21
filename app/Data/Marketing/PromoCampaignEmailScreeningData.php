<?php

declare(strict_types=1);

namespace App\Data\Marketing;

final readonly class PromoCampaignEmailScreeningData
{
    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<PromoCampaignSkippedEmailData>  $skipped
     */
    public function __construct(
        public array $rows,
        public int $emailsKept,
        public int $emailsSkipped,
        public array $skipped,
    ) {}
}
