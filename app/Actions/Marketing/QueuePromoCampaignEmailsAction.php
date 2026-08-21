<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Jobs\SendPromoCampaignEmailJob;
use App\Models\EmailUnsubscribe;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignEmailSend;
use App\Models\PromoCampaignTarget;
use App\Support\EmailUnsubscribeExemptions;
use RuntimeException;

class QueuePromoCampaignEmailsAction
{
    public const DISABLED_MESSAGE = 'promo_campaign_emails_disabled';

    public const PAUSED_MESSAGE = 'promo_campaign_emails_paused';

    public function __construct(private LogAuditAction $logAudit) {}

    /**
     * @return array{queued: int, skipped: int}
     */
    public function preview(PromoCampaign $campaign, bool $forceResend = false): array
    {
        $resolved = $this->resolveTargets($campaign, $forceResend);

        return [
            'queued' => $resolved['queued'],
            'skipped' => $resolved['skipped'],
        ];
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
        $this->assertSendingAllowed($campaign);

        $delaySeconds = max(0, $delaySeconds);
        if ($delaySeconds > 0) {
            $delaySeconds = max(
                $delaySeconds,
                (int) config('winprox.promo_campaign_email_min_interval_seconds', 1),
            );
        }
        $resolved = $this->resolveTargets($campaign, $forceResend);
        $queueIndex = 0;

        foreach ($resolved['unsubscribed_targets'] as $target) {
            $this->markUnsubscribedSkipped($campaign, $target, $actorUserId);
        }

        foreach ($resolved['targets'] as $target) {
            SendPromoCampaignEmailJob::dispatch(
                promoCampaignId: (int) $campaign->id,
                promoCampaignTargetId: (int) $target->id,
                actorUserId: $actorUserId,
                overrideRecipientEmail: null,
            )->delay(now()->addSeconds($queueIndex * $delaySeconds));

            $queueIndex++;
        }

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_campaign_emails_queued',
            modelType: 'PromoCampaign',
            modelId: $campaign->id,
            payload: [
                'promo_campaign_id' => $campaign->id,
                'slug' => $campaign->slug,
                'queued' => $resolved['queued'],
                'skipped' => $resolved['skipped'],
                'force_resend' => $forceResend,
                'target_count' => $resolved['queued'],
            ],
        );

        return [
            'queued' => $resolved['queued'],
            'skipped' => $resolved['skipped'],
        ];
    }

    /**
     * @return array{
     *     queued: int,
     *     skipped: int,
     *     targets: list<PromoCampaignTarget>,
     *     unsubscribed_targets: list<PromoCampaignTarget>
     * }
     */
    private function resolveTargets(PromoCampaign $campaign, bool $forceResend): array
    {
        $queued = 0;
        $skipped = 0;
        /** @var list<PromoCampaignTarget> $targets */
        $targets = [];
        /** @var list<PromoCampaignTarget> $unsubscribedTargets */
        $unsubscribedTargets = [];

        $sentTargetIds = [];
        if (! $forceResend) {
            $sentTargetIds = $campaign->emailSends()
                ->where('status', MunicipalPromoEmailSendStatus::Sent)
                ->pluck('promo_campaign_target_id')
                ->all();
        }

        $bouncedTargetIds = $campaign->emailSends()
            ->where('status', MunicipalPromoEmailSendStatus::Bounced)
            ->pluck('promo_campaign_target_id')
            ->all();

        $attachLetter = $campaign->attach_letter_to_email;

        foreach ($campaign->targets()->orderBy('id')->get() as $target) {
            if ($target->undelivered) {
                $skipped++;

                continue;
            }

            if ($attachLetter && ($target->generated_at === null || $target->docx_filename === null)) {
                $skipped++;

                continue;
            }

            if (in_array($target->id, $bouncedTargetIds, true)) {
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

            $normalizedEmail = EmailUnsubscribe::normalizeEmail((string) $target->email);
            if (
                EmailUnsubscribe::isUnsubscribed($normalizedEmail)
                && ! EmailUnsubscribeExemptions::isExempt($normalizedEmail)
            ) {
                $unsubscribedTargets[] = $target;
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
            'unsubscribed_targets' => $unsubscribedTargets,
        ];
    }

    private function markUnsubscribedSkipped(
        PromoCampaign $campaign,
        PromoCampaignTarget $target,
        int $actorUserId,
    ): void {
        $send = PromoCampaignEmailSend::query()->firstOrNew([
            'promo_campaign_id' => $campaign->id,
            'promo_campaign_target_id' => $target->id,
        ]);

        if ($send->exists && in_array($send->status, [
            MunicipalPromoEmailSendStatus::Sent,
            MunicipalPromoEmailSendStatus::Bounced,
        ], true)) {
            return;
        }

        $send->fill([
            'recipient_email' => EmailUnsubscribe::normalizeEmail((string) $target->email),
            'status' => MunicipalPromoEmailSendStatus::Skipped,
            'error_message' => 'unsubscribed',
            'sent_at' => null,
            'created_by' => $actorUserId,
        ]);
        $send->save();
    }

    private function assertSendingAllowed(PromoCampaign $campaign): void
    {
        if (! (bool) config('winprox.promo_campaign_emails_enabled', true)) {
            throw new RuntimeException(self::DISABLED_MESSAGE);
        }

        if ($campaign->isEmailSendingPaused()) {
            throw new RuntimeException(self::PAUSED_MESSAGE);
        }
    }
}
