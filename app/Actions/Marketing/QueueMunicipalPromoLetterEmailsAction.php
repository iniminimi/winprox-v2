<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\MunicipalPromoEmailCandidateData;
use App\Jobs\SendMunicipalPromoLetterEmailJob;
use RuntimeException;

class QueueMunicipalPromoLetterEmailsAction
{
    /**
     * @param  list<MunicipalPromoEmailCandidateData>  $candidates
     * @return array{queued: int, skipped: int}
     */
    public function handle(
        array $candidates,
        string $campaign,
        string $spreadsheetPath,
        string $lettersDirectory,
        string $promoBaseUrl,
        int $actorUserId,
        int $delaySeconds,
        ?string $overrideRecipientEmail = null,
        bool $forceResend = false,
    ): array {
        if (! (bool) config('winprox.promo_campaign_emails_enabled', true)) {
            throw new RuntimeException(QueuePromoCampaignEmailsAction::DISABLED_MESSAGE);
        }

        $delaySeconds = max(0, $delaySeconds);
        if ($delaySeconds > 0) {
            $delaySeconds = max(
                $delaySeconds,
                (int) config('winprox.promo_campaign_email_min_interval_seconds', 1),
            );
        }
        $queued = 0;
        $skipped = 0;
        $queueIndex = 0;

        foreach ($candidates as $candidate) {
            if (! $candidate->isReady()) {
                $skipped++;

                continue;
            }

            SendMunicipalPromoLetterEmailJob::dispatch(
                municipalityName: $candidate->municipality->name,
                campaign: $campaign,
                spreadsheetPath: $spreadsheetPath,
                lettersDirectory: $lettersDirectory,
                promoBaseUrl: $promoBaseUrl,
                actorUserId: $actorUserId,
                overrideRecipientEmail: $overrideRecipientEmail,
                forceResend: $forceResend,
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
