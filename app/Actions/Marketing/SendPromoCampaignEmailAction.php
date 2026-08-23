<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Mail\Marketing\PromoCampaignLetterMail;
use App\Models\EmailUnsubscribe;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignEmailSend;
use App\Models\PromoCampaignTarget;
use App\Models\PromoRecipient;
use App\Support\EmailUnsubscribeExemptions;
use App\Support\Marketing\PromoCampaignPlaceholderRenderer;
use App\Support\Marketing\PromoCampaignQuillHtmlNormalizer;
use App\Support\Marketing\PromoCampaignYoutubeThumbnail;
use App\Support\Marketing\PromoLandingUrl;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendPromoCampaignEmailAction
{
    public function __construct(
        private LogAuditAction $logAudit,
        private CreatePromoRecipientAction $createPromoRecipient,
    ) {}

    public function handle(
        PromoCampaign $campaign,
        PromoCampaignTarget $target,
        int $actorUserId,
        ?string $overrideRecipientEmail = null,
    ): ?PromoCampaignEmailSend {
        if ((int) $target->promo_campaign_id !== (int) $campaign->id) {
            throw new RuntimeException('Target does not belong to campaign.');
        }

        $target->loadMissing('promoRecipient');

        $recipientEmail = trim((string) ($overrideRecipientEmail ?? $target->email));
        if ($recipientEmail === '' || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Invalid recipient email.');
        }

        $isTestSend = $overrideRecipientEmail !== null;
        if (! $isTestSend) {
            if (! (bool) config('winprox.promo_campaign_emails_enabled', true)) {
                throw new RuntimeException(QueuePromoCampaignEmailsAction::DISABLED_MESSAGE);
            }
            if ($campaign->isEmailSendingPaused()) {
                throw new RuntimeException(QueuePromoCampaignEmailsAction::PAUSED_MESSAGE);
            }
        }

        if ($target->undelivered && ! $isTestSend) {
            throw new RuntimeException('Email previously bounced for this target.');
        }

        $normalizedRecipientEmail = EmailUnsubscribe::normalizeEmail($recipientEmail);
        $isUnsubscribed = EmailUnsubscribe::isUnsubscribed($normalizedRecipientEmail)
            && ! EmailUnsubscribeExemptions::isExempt($normalizedRecipientEmail);

        if ($isUnsubscribed && $isTestSend) {
            throw new RuntimeException('Recipient is unsubscribed.');
        }

        if ($isUnsubscribed && ! $isTestSend) {
            return $this->markUnsubscribedSkipped($campaign, $target, $normalizedRecipientEmail, $actorUserId);
        }

        $emailSubject = trim((string) ($campaign->email_subject ?? ''));
        $emailBodyHtml = (string) ($campaign->email_body_html ?? '');
        if ($emailSubject === '' || $emailBodyHtml === '') {
            throw new RuntimeException('Email subject and body are required.');
        }

        $recipient = $this->resolvePromoRecipient($campaign, $target, $actorUserId);
        $promoUrl = PromoLandingUrl::forRecipientToken(
            $recipient->token,
            $campaign->locale,
            $campaign->landing,
        );
        $welcomeUrl = PromoLandingUrl::welcomeForRecipientToken(
            $recipient->token,
            $campaign->locale,
        );

        $placeholders = array_merge(
            PromoCampaignPlaceholderRenderer::forTarget(
                name: $target->name,
                streetAddress: $target->street_address,
                postalCode: $target->postal_code,
                city: $target->city,
                email: $target->email,
                promoUrl: $promoUrl,
                welcomeUrl: $welcomeUrl,
            ),
            [
                'youtube_url' => trim((string) ($campaign->youtube_url ?? '')),
            ],
        );

        $emailSubject = PromoCampaignPlaceholderRenderer::render($emailSubject, $placeholders);
        $emailBodyHtml = PromoCampaignYoutubeThumbnail::expandInMailHtml(
            PromoCampaignQuillHtmlNormalizer::forMail(
                PromoCampaignPlaceholderRenderer::render($emailBodyHtml, $placeholders),
            ),
            $campaign->youtube_url,
        );

        $send = null;

        if (! $isTestSend) {
            $send = PromoCampaignEmailSend::query()->firstOrNew([
                'promo_campaign_id' => $campaign->id,
                'promo_campaign_target_id' => $target->id,
            ]);

            if ($send->exists && $send->status === MunicipalPromoEmailSendStatus::Sent) {
                throw new RuntimeException('Email already sent for this target.');
            }

            if ($send->exists && $send->status === MunicipalPromoEmailSendStatus::Bounced) {
                throw new RuntimeException('Email previously bounced for this target.');
            }

            $send->fill([
                'recipient_email' => $recipientEmail,
                'status' => MunicipalPromoEmailSendStatus::Pending,
                'error_message' => null,
                'sent_at' => null,
                'created_by' => $actorUserId,
            ]);
            $send->save();
        }

        try {
            Mail::to($recipientEmail)->send(new PromoCampaignLetterMail(
                emailSubject: $emailSubject,
                emailBodyHtml: $emailBodyHtml,
                mailLocale: $campaign->locale,
            ));

            if ($send !== null) {
                $send->update([
                    'status' => MunicipalPromoEmailSendStatus::Sent,
                    'sent_at' => now(),
                ]);
            }
        } catch (Throwable $exception) {
            if ($send !== null) {
                $send->update([
                    'status' => MunicipalPromoEmailSendStatus::Failed,
                    'error_message' => mb_substr($exception->getMessage(), 0, 1000),
                ]);
            }

            throw $exception;
        }

        if ($isTestSend) {
            $this->logAudit->handle(
                userId: $actorUserId,
                tenantId: null,
                action: 'marketing.promo_campaign_test_email_sent',
                modelType: 'PromoCampaignTarget',
                modelId: $target->id,
                payload: [
                    'promo_campaign_id' => $campaign->id,
                    'promo_campaign_target_id' => $target->id,
                    'recipient_email' => $recipientEmail,
                ],
            );

            return null;
        }

        return $send->fresh() ?? $send;
    }

    private function markUnsubscribedSkipped(
        PromoCampaign $campaign,
        PromoCampaignTarget $target,
        string $normalizedRecipientEmail,
        int $actorUserId,
    ): PromoCampaignEmailSend {
        $send = PromoCampaignEmailSend::query()->firstOrNew([
            'promo_campaign_id' => $campaign->id,
            'promo_campaign_target_id' => $target->id,
        ]);

        if ($send->exists && in_array($send->status, [
            MunicipalPromoEmailSendStatus::Sent,
            MunicipalPromoEmailSendStatus::Bounced,
        ], true)) {
            throw new RuntimeException('Email already sent for this target.');
        }

        $send->fill([
            'recipient_email' => $normalizedRecipientEmail,
            'status' => MunicipalPromoEmailSendStatus::Skipped,
            'error_message' => 'unsubscribed',
            'sent_at' => null,
            'created_by' => $actorUserId,
        ]);
        $send->save();

        return $send->fresh() ?? $send;
    }

    private function resolvePromoRecipient(
        PromoCampaign $campaign,
        PromoCampaignTarget $target,
        int $actorUserId,
    ): PromoRecipient {
        if ($target->promo_recipient_id !== null) {
            $existing = PromoRecipient::query()->find($target->promo_recipient_id);
            if ($existing instanceof PromoRecipient) {
                return $existing;
            }
        }

        $byLabel = PromoRecipient::query()->where('label', $target->name)->first();
        if ($byLabel instanceof PromoRecipient) {
            if ($target->promo_recipient_id !== $byLabel->id) {
                $target->update(['promo_recipient_id' => $byLabel->id]);
            }

            return $byLabel;
        }

        $recipient = $this->createPromoRecipient->handle(
            label: $target->name,
            note: $campaign->name,
            actorUserId: $actorUserId,
            recordAudit: false,
        );

        $target->update(['promo_recipient_id' => $recipient->id]);

        return $recipient;
    }
}
