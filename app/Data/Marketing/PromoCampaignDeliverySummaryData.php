<?php

declare(strict_types=1);

namespace App\Data\Marketing;

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
        public int $remaining,
        public int $queuedJobs,
        public string $status,
        public ?Carbon $firstSentAt = null,
        public ?Carbon $lastSentAt = null,
    ) {}

    public function pillClass(): string
    {
        return match ($this->status) {
            'complete' => 'wp-pill wp-pill--done',
            'sending' => 'wp-pill wp-pill--progress',
            'paused' => 'wp-pill wp-pill--new',
            'needs_restart' => 'wp-pill wp-pill--new',
            'not_started' => 'wp-pill wp-pill--closed',
            default => 'wp-pill wp-pill--closed',
        };
    }
}
