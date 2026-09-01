<?php

declare(strict_types=1);

namespace App\Data\Marketing;

use App\Enums\PromoCampaignDeliveryStatus;
use Illuminate\Support\Carbon;

final readonly class PromoCampaignDeliverySummaryData
{
    public function __construct(
        public int $targets,
        public int $withEmail,
        public int $sent,
        public int $failed,
        public int $skipped,
        public int $bounced,
        public int $bouncePercent,
        public int $bounceUnknown,
        public int $bounceBlacklist,
        public int $bounceMailboxFull,
        public int $bounceSpam,
        public int $bounceDomainBlock,
        public int $bounceOther,
        public int $remaining,
        public int $queuedJobs,
        public PromoCampaignDeliveryStatus $status,
        public ?Carbon $firstSentAt = null,
        public ?Carbon $lastSentAt = null,
    ) {}

    public function pillClass(): string
    {
        return $this->status->pillClass();
    }
}
