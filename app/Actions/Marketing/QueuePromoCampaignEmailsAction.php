<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Enums\MunicipalPromoEmailSendStatus;
use App\Jobs\SendPromoCampaignEmailJob;
use App\Models\PromoCampaign;

class QueuePromoCampaignEmailsAction
{
    /**
     * @return array{queued: int, skipped: int}
     */
    public function handle(
        PromoCampaign $campaign,
        int $actorUserId,
        int $delaySeconds,
        ?string $overrideRecipientEmail = null,
        bool $forceResend = false,
    ): array {
        $delaySeconds = max(0, $delaySeconds);
        $queued = 0;
        $skipped = 0;
        $queueIndex = 0;

        $sentTargetIds = [];
        if (! $forceResend) {
            $sentTargetIds = $campaign->emailSends()
                ->where('status', MunicipalPromoEmailSendStatus::Sent)
                ->pluck('promo_campaign_target_id')
                ->all();
        }

        foreach ($campaign->targets()->orderBy('id')->get() as $target) {
            if ($target->generated_at === null || $target->docx_filename === null) {
                $skipped++;

                continue;
            }

            if (! $forceResend && in_array($target->id, $sentTargetIds, true)) {
                $skipped++;

                continue;
            }

            if ($overrideRecipientEmail === null && ($target->email === null || $target->email === '')) {
                $skipped++;

                continue;
            }

            SendPromoCampaignEmailJob::dispatch(
                promoCampaignId: (int) $campaign->id,
                promoCampaignTargetId: (int) $target->id,
                actorUserId: $actorUserId,
                overrideRecipientEmail: $overrideRecipientEmail,
            )->delay(now()->addSeconds($queueIndex * $delaySeconds));

            $queueIndex++;
            $queued++;
        }

        return [
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }
}
