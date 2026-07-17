<?php

namespace App\Jobs;

use App\Actions\Marketing\SendPromoCampaignEmailAction;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignTarget;
use App\Support\Marketing\PromoSmtpThrottle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPromoCampaignEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 25;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(
        public int $promoCampaignId,
        public int $promoCampaignTargetId,
        public int $actorUserId,
        public ?string $overrideRecipientEmail = null,
    ) {}

    public function handle(SendPromoCampaignEmailAction $sendEmail): void
    {
        $campaign = PromoCampaign::query()->find($this->promoCampaignId);
        $target = PromoCampaignTarget::query()->with('promoRecipient')->find($this->promoCampaignTargetId);

        if ($campaign === null || $target === null) {
            Log::warning('promo_campaign_email_job_skipped', [
                'promo_campaign_id' => $this->promoCampaignId,
                'promo_campaign_target_id' => $this->promoCampaignTargetId,
                'reason' => 'not_found',
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

        try {
            $sendEmail->handle(
                campaign: $campaign,
                target: $target,
                actorUserId: $this->actorUserId,
                overrideRecipientEmail: $this->overrideRecipientEmail,
            );
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Email already sent for this target.') {
                Log::info('promo_campaign_email_job_skipped', [
                    'promo_campaign_id' => $this->promoCampaignId,
                    'promo_campaign_target_id' => $this->promoCampaignTargetId,
                    'reason' => 'already_sent',
                ]);

                return;
            }

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('promo_campaign_email_job_failed', [
            'promo_campaign_id' => $this->promoCampaignId,
            'promo_campaign_target_id' => $this->promoCampaignTargetId,
            'message' => $exception->getMessage(),
        ]);
    }
}
