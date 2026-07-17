<?php

namespace App\Jobs;

use App\Actions\Marketing\ListMunicipalPromoEmailCandidatesAction;
use App\Actions\Marketing\SendMunicipalPromoLetterEmailAction;
use App\Support\Marketing\PromoSmtpThrottle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMunicipalPromoLetterEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 25;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(
        public string $municipalityName,
        public string $campaign,
        public string $spreadsheetPath,
        public string $lettersDirectory,
        public string $promoBaseUrl,
        public int $actorUserId,
        public ?string $overrideRecipientEmail = null,
        public bool $forceResend = false,
    ) {}

    public function handle(
        ListMunicipalPromoEmailCandidatesAction $listCandidates,
        SendMunicipalPromoLetterEmailAction $sendEmail,
    ): void {
        $candidates = $listCandidates->handle(
            spreadsheetPath: $this->spreadsheetPath,
            lettersDirectory: $this->lettersDirectory,
            promoBaseUrl: $this->promoBaseUrl,
            campaign: $this->campaign,
            municipalityFilter: $this->municipalityName,
            forceResend: $this->forceResend,
            overrideRecipientEmail: $this->overrideRecipientEmail,
        );

        $candidate = $candidates[0] ?? null;
        if ($candidate === null || ! $candidate->isReady()) {
            Log::warning('municipal_promo_email_job_skipped', [
                'municipality' => $this->municipalityName,
                'campaign' => $this->campaign,
                'reason' => $candidate?->blockReason ?? 'not_found',
            ]);

            return;
        }

        $waitSeconds = PromoSmtpThrottle::secondsUntilAvailable();
        if ($waitSeconds !== null) {
            $this->release($waitSeconds);

            return;
        }

        if (! PromoSmtpThrottle::tryAcquire()) {
            $this->release(PromoSmtpThrottle::intervalSeconds());

            return;
        }

        $sendEmail->handle(
            candidate: $candidate,
            campaign: $this->campaign,
            actorUserId: $this->actorUserId,
            overrideRecipientEmail: $this->overrideRecipientEmail,
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('municipal_promo_email_job_failed', [
            'municipality' => $this->municipalityName,
            'campaign' => $this->campaign,
            'message' => $exception->getMessage(),
        ]);
    }
}
