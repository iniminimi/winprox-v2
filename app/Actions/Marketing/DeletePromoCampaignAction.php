<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Models\PromoCampaign;
use Illuminate\Support\Facades\File;

class DeletePromoCampaignAction
{
    public function __construct(
        private LogAuditAction $logAudit,
        private PausePromoCampaignSendingAction $pauseSending,
    ) {}

    public function handle(PromoCampaign $campaign, int $actorUserId): void
    {
        $slug = $campaign->slug;
        $id = (int) $campaign->id;
        $campaignDirectory = dirname($campaign->lettersDirectory());

        $this->pauseSending->handle($campaign, $actorUserId);

        $campaign->delete();

        if (is_dir($campaignDirectory)) {
            File::deleteDirectory($campaignDirectory);
        }

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_campaign_deleted',
            modelType: 'PromoCampaign',
            modelId: $id,
            payload: [
                'slug' => $slug,
            ],
        );
    }
}
