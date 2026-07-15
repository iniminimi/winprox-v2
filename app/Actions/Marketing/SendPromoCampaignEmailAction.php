<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Mail\Marketing\PromoCampaignLetterMail;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignEmailSend;
use App\Models\PromoCampaignTarget;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoCampaignPlaceholderRenderer;
use App\Support\Marketing\PromoCampaignQuillHtmlNormalizer;
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

        $attachLetter = $campaign->attach_letter_to_email;

        if ($attachLetter) {
            if ($target->docx_filename === null || $target->generated_at === null) {
                throw new RuntimeException('Letter has not been generated for this target.');
            }
        }

        $target->loadMissing('promoRecipient');

        $recipientEmail = trim((string) ($overrideRecipientEmail ?? $target->email));
        if ($recipientEmail === '' || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Invalid recipient email.');
        }

        $docxPath = null;
        if ($attachLetter) {
            $docxPath = $campaign->lettersDirectory().DIRECTORY_SEPARATOR.$target->docx_filename;
            if (! is_file($docxPath)) {
                throw new RuntimeException('DOCX file not found for target.');
            }
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
        );

        $placeholders = PromoCampaignPlaceholderRenderer::forTarget(
            name: $target->name,
            streetAddress: $target->street_address,
            postalCode: $target->postal_code,
            city: $target->city,
            email: $target->email,
            promoUrl: $promoUrl,
        );

        $emailSubject = PromoCampaignPlaceholderRenderer::render($emailSubject, $placeholders);
        $emailBodyHtml = PromoCampaignQuillHtmlNormalizer::forMail(
            PromoCampaignPlaceholderRenderer::render($emailBodyHtml, $placeholders),
        );

        $isTestSend = $overrideRecipientEmail !== null;
        $send = null;

        if (! $isTestSend) {
            $send = PromoCampaignEmailSend::query()->firstOrNew([
                'promo_campaign_id' => $campaign->id,
                'promo_campaign_target_id' => $target->id,
            ]);

            if ($send->exists && $send->status === MunicipalPromoEmailSendStatus::Sent) {
                throw new RuntimeException('Email already sent for this target.');
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
                docxPath: $docxPath,
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
                    'attach_letter' => $attachLetter,
                ],
            );

            return null;
        }

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_campaign_email_sent',
            modelType: 'PromoCampaignEmailSend',
            modelId: $send->id,
            payload: [
                'promo_campaign_id' => $campaign->id,
                'promo_campaign_target_id' => $target->id,
                'recipient_email' => $recipientEmail,
                'override_recipient' => false,
                'attach_letter' => $attachLetter,
            ],
        );

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
        );

        $target->update(['promo_recipient_id' => $recipient->id]);

        return $recipient;
    }
}
