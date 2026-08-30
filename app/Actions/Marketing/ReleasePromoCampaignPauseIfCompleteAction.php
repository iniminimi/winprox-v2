<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Enums\PromoCampaignDeliveryStatus;
use App\Models\PromoCampaign;
use Illuminate\Support\Collection;

class ReleasePromoCampaignPauseIfCompleteAction
{
    public function __construct(
        private SummarizePromoCampaignsDeliveryAction $summarize,
        private LogAuditAction $logAudit,
    ) {}

    /**
     * @param  Collection<int, PromoCampaign>|iterable<PromoCampaign>  $campaigns
     */
    public function handleCollection(iterable $campaigns, ?int $actorUserId = null): int
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
            if ($summary?->status !== PromoCampaignDeliveryStatus::Complete) {
                continue;
            }

            if ($this->release($campaign, $actorUserId)) {
                $released++;
            }
        }

        return $released;
    }

    public function handle(PromoCampaign $campaign, ?int $actorUserId = null): bool
    {
        if (! $campaign->isEmailSendingPaused()) {
            return false;
        }

        $summary = $this->summarize->handle(collect([$campaign]))[(int) $campaign->id] ?? null;
        if ($summary?->status !== PromoCampaignDeliveryStatus::Complete) {
            return false;
        }

        return $this->release($campaign, $actorUserId);
    }

    private function release(PromoCampaign $campaign, ?int $actorUserId): bool
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

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_campaign_pause_released_complete',
            modelType: 'PromoCampaign',
            modelId: $campaign->id,
            payload: [
                'promo_campaign_id' => $campaign->id,
                'automatic' => $actorUserId === null,
            ],
        );

        return true;
    }
}
