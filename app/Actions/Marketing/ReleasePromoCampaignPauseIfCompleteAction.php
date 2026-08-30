<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\PromoCampaign;
use Illuminate\Support\Collection;

class ReleasePromoCampaignPauseIfCompleteAction
{
    public function __construct(
        private SummarizePromoCampaignsDeliveryAction $summarize,
    ) {}

    /**
     * @param  Collection<int, PromoCampaign>|iterable<PromoCampaign>  $campaigns
     */
    public function handleCollection(iterable $campaigns): int
    {
        $paused = Collection::make($campaigns)->filter(
            fn (PromoCampaign $campaign): bool => $campaign->isEmailSendingPaused(),
        );

        if ($paused->isEmpty()) {
            return 0;
        }

        $summaries = $this->summarize->handle($paused);
        $released = 0;

        foreach ($paused as $campaign) {
            $summary = $summaries[(int) $campaign->id] ?? null;
            if ($summary?->status !== 'complete') {
                continue;
            }

            if ($this->release($campaign)) {
                $released++;
            }
        }

        return $released;
    }

    public function handle(PromoCampaign $campaign): bool
    {
        if (! $campaign->isEmailSendingPaused()) {
            return false;
        }

        $summary = $this->summarize->handle(collect([$campaign]))[(int) $campaign->id] ?? null;
        if ($summary?->status !== 'complete') {
            return false;
        }

        return $this->release($campaign);
    }

    private function release(PromoCampaign $campaign): bool
    {
        $updated = PromoCampaign::query()
            ->whereKey($campaign->id)
            ->whereNotNull('emails_paused_at')
            ->update([
                'emails_paused_at' => null,
                'emails_paused_reason' => null,
                'emails_paused_detail' => null,
            ]);

        if ($updated === 0) {
            return false;
        }

        $campaign->forceFill([
            'emails_paused_at' => null,
            'emails_paused_reason' => null,
            'emails_paused_detail' => null,
        ])->syncOriginal();

        return true;
    }
}
