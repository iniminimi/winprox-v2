<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Enums\MunicipalPromoEmailSendStatus;
use App\Jobs\SendPromoCampaignEmailJob;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignTarget;

class QueuePromoCampaignEmailsAction
{
    /**
     * @return array{queued: int, skipped: int}
     */
    public function preview(PromoCampaign $campaign, bool $forceResend = false): array
    {
        return $this->resolveTargets($campaign, $forceResend);
    }

    /**
     * @return array{queued: int, skipped: int}
     */
    public function handle(
        PromoCampaign $campaign,
        int $actorUserId,
        int $delaySeconds,
        bool $forceResend = false,
    ): array {
        $delaySeconds = max(0, $delaySeconds);
        $resolved = $this->resolveTargets($campaign, $forceResend);
        $queueIndex = 0;

        foreach ($resolved['targets'] as $target) {
            SendPromoCampaignEmailJob::dispatch(
                promoCampaignId: (int) $campaign->id,
                promoCampaignTargetId: (int) $target->id,
                actorUserId: $actorUserId,
                overrideRecipientEmail: null,
            )->delay(now()->addSeconds($queueIndex * $delaySeconds));

            $queueIndex++;
        }

        return [
            'queued' => $resolved['queued'],
            'skipped' => $resolved['skipped'],
        ];
    }

    /**
     * @return array{queued: int, skipped: int, targets: list<PromoCampaignTarget>}
     */
    private function resolveTargets(PromoCampaign $campaign, bool $forceResend): array
    {
        $queued = 0;
        $skipped = 0;
        /** @var list<PromoCampaignTarget> $targets */
        $targets = [];

        $sentTargetIds = [];
        if (! $forceResend) {
            $sentTargetIds = $campaign->emailSends()
                ->where('status', MunicipalPromoEmailSendStatus::Sent)
                ->pluck('promo_campaign_target_id')
                ->all();
        }

        $attachLetter = $campaign->attach_letter_to_email;

        foreach ($campaign->targets()->orderBy('id')->get() as $target) {
            if ($attachLetter && ($target->generated_at === null || $target->docx_filename === null)) {
                $skipped++;

                continue;
            }

            if (! $forceResend && in_array($target->id, $sentTargetIds, true)) {
                $skipped++;

                continue;
            }

            if ($target->email === null || trim((string) $target->email) === '') {
                $skipped++;

                continue;
            }

            $targets[] = $target;
            $queued++;
        }

        return [
            'queued' => $queued,
            'skipped' => $skipped,
            'targets' => $targets,
        ];
    }
}
