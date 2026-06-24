<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Data\Marketing\MunicipalPromoEmailCandidateData;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Mail\Marketing\MunicipalPromoLetterMail;
use App\Models\MunicipalPromoEmailSend;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendMunicipalPromoLetterEmailAction
{
    public function __construct(private LogAuditAction $logAudit) {}

    public function handle(
        MunicipalPromoEmailCandidateData $candidate,
        string $campaign,
        int $actorUserId,
        ?string $overrideRecipientEmail = null,
    ): MunicipalPromoEmailSend {
        if (! $candidate->isReady()) {
            throw new RuntimeException('Candidate is not ready to send: '.$candidate->blockReason);
        }

        $recipientEmail = trim((string) ($overrideRecipientEmail ?? $candidate->recipientEmail));
        if ($recipientEmail === '' || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Invalid recipient email.');
        }

        $campaign = trim($campaign);
        if ($campaign === '') {
            throw new RuntimeException('Campaign is required.');
        }

        $send = MunicipalPromoEmailSend::query()->firstOrNew([
            'campaign' => $campaign,
            'municipality_name' => $candidate->municipality->name,
        ]);

        if (
            $send->exists
            && $send->status === MunicipalPromoEmailSendStatus::Sent
            && $overrideRecipientEmail === null
        ) {
            throw new RuntimeException('Email already sent for this municipality in this campaign.');
        }

        $send->fill([
            'promo_recipient_id' => $candidate->promoRecipientId,
            'recipient_email' => $recipientEmail,
            'docx_filename' => basename($candidate->docxPath),
            'status' => MunicipalPromoEmailSendStatus::Pending,
            'error_message' => null,
            'sent_at' => null,
            'created_by' => $actorUserId,
        ]);
        $send->save();

        try {
            Mail::to($recipientEmail)->send(new MunicipalPromoLetterMail(
                municipalityName: $candidate->municipality->name,
                promoUrl: $candidate->promoUrl,
                docxPath: $candidate->docxPath,
            ));

            $send->update([
                'status' => MunicipalPromoEmailSendStatus::Sent,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $send->update([
                'status' => MunicipalPromoEmailSendStatus::Failed,
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            throw $exception;
        }

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.municipal_promo_email_sent',
            modelType: 'MunicipalPromoEmailSend',
            modelId: $send->id,
            payload: [
                'campaign' => $campaign,
                'municipality_name' => $candidate->municipality->name,
                'recipient_email' => $recipientEmail,
                'promo_recipient_id' => $candidate->promoRecipientId,
                'override_recipient' => $overrideRecipientEmail !== null,
            ],
        );

        return $send->fresh() ?? $send;
    }
}
