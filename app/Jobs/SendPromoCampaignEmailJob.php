<?php

namespace App\Jobs;

use App\Actions\Marketing\SendPromoCampaignEmailAction;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignEmailSend;
use App\Models\PromoCampaignTarget;
use App\Support\Marketing\PromoSmtpThrottle;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPromoCampaignEmailJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The SMTP throttle releases this job once per interval, and every release burns an
     * attempt. A bulk campaign therefore needs a deadline instead of a try limit, plus a
     * separate cap on real send errors.
     */
    public int $maxExceptions = 3;

    public int $timeout = 30;

    public int $uniqueFor = 86400;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDays(3);
    }

    public function uniqueId(): string
    {
        return $this->promoCampaignId.'-'.$this->promoCampaignTargetId;
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

        if (! (bool) config('winprox.promo_campaign_emails_enabled', true)) {
            Log::info('promo_campaign_email_job_skipped', [
                'promo_campaign_id' => $this->promoCampaignId,
                'promo_campaign_target_id' => $this->promoCampaignTargetId,
                'reason' => 'disabled',
            ]);

            return;
        }

        if ($campaign->isEmailSendingPaused()) {
            Log::info('promo_campaign_email_job_skipped', [
                'promo_campaign_id' => $this->promoCampaignId,
                'promo_campaign_target_id' => $this->promoCampaignTargetId,
                'reason' => 'paused',
            ]);

            return;
        }

        if ($this->alreadyDelivered($campaign, $target)) {
            Log::info('promo_campaign_email_job_skipped', [
                'promo_campaign_id' => $this->promoCampaignId,
                'promo_campaign_target_id' => $this->promoCampaignTargetId,
                'reason' => 'already_delivered',
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

            if ($exception->getMessage() === 'Email previously bounced for this target.') {
                Log::info('promo_campaign_email_job_skipped', [
                    'promo_campaign_id' => $this->promoCampaignId,
                    'promo_campaign_target_id' => $this->promoCampaignTargetId,
                    'reason' => 'bounced',
                ]);

                return;
            }

            if (in_array($exception->getMessage(), [
                'promo_campaign_emails_paused',
                'promo_campaign_emails_disabled',
            ], true)) {
                Log::info('promo_campaign_email_job_skipped', [
                    'promo_campaign_id' => $this->promoCampaignId,
                    'promo_campaign_target_id' => $this->promoCampaignTargetId,
                    'reason' => $exception->getMessage(),
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

    private function alreadyDelivered(PromoCampaign $campaign, PromoCampaignTarget $target): bool
    {
        if ($this->overrideRecipientEmail !== null) {
            return false;
        }

        if ($target->undelivered) {
            return true;
        }

        $status = PromoCampaignEmailSend::query()
            ->where('promo_campaign_id', $campaign->id)
            ->where('promo_campaign_target_id', $target->id)
            ->value('status');

        $statusValue = $status instanceof \BackedEnum ? $status->value : $status;

        return in_array($statusValue, [
            MunicipalPromoEmailSendStatus::Sent->value,
            MunicipalPromoEmailSendStatus::Bounced->value,
        ], true);
    }
}
