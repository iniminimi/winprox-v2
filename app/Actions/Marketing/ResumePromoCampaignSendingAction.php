<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Models\PromoCampaign;

class ResumePromoCampaignSendingAction
{
    public function __construct(private LogAuditAction $logAudit) {}

    /**
     * Allow queueing again. Does not put leftover recipients back on the queue.
     *
     * @return array{resumed_campaigns: int}
     */
    public function handle(?PromoCampaign $campaign, ?int $actorUserId): array
    {
        $query = PromoCampaign::query()->whereNotNull('emails_paused_at');
        if ($campaign !== null) {
            $query->whereKey($campaign->id);
        }

        $resumedIds = $query->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($resumedIds !== []) {
            PromoCampaign::query()
                ->whereIn('id', $resumedIds)
                ->update(['emails_paused_at' => null]);
        }

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_campaign_emails_resumed',
            modelType: $campaign !== null ? 'PromoCampaign' : null,
            modelId: $campaign?->id,
            payload: [
                'campaign_ids' => $resumedIds,
                'all' => $campaign === null,
            ],
        );

        return [
            'resumed_campaigns' => count($resumedIds),
        ];
    }
}
