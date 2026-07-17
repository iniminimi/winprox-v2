<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Actions\Contact\SetEmailSubscriptionAction;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Models\EmailUnsubscribe;
use App\Models\PromoCampaignEmailSend;

class MarkPromoCampaignEmailBouncedAction
{
    public function __construct(
        private SetEmailSubscriptionAction $setEmailSubscription,
        private LogAuditAction $logAudit,
    ) {}

    /**
     * Mark matching promo sends as bounced and block the address for future campaigns.
     *
     * @return array{marked: int, already_bounced: int, blocked: bool}
     */
    public function handle(string $recipientEmail, ?string $reason = null): array
    {
        $normalized = EmailUnsubscribe::normalizeEmail($recipientEmail);
        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            return ['marked' => 0, 'already_bounced' => 0, 'blocked' => false];
        }

        $reason = mb_substr(trim((string) ($reason ?: 'bounce')), 0, 1000);
        $marked = 0;
        $alreadyBounced = 0;

        $sends = PromoCampaignEmailSend::query()
            ->whereRaw('LOWER(recipient_email) = ?', [$normalized])
            ->get();

        foreach ($sends as $send) {
            if ($send->status === MunicipalPromoEmailSendStatus::Bounced) {
                $alreadyBounced++;

                continue;
            }

            $send->update([
                'status' => MunicipalPromoEmailSendStatus::Bounced,
                'error_message' => $reason,
            ]);
            $marked++;

            $this->logAudit->handle(
                userId: null,
                tenantId: null,
                action: 'marketing.promo_campaign_email_bounced',
                modelType: 'PromoCampaignEmailSend',
                modelId: $send->id,
                payload: [
                    'promo_campaign_id' => $send->promo_campaign_id,
                    'promo_campaign_target_id' => $send->promo_campaign_target_id,
                    'recipient_email' => $normalized,
                    'reason' => $reason,
                ],
            );
        }

        $wasBlocked = EmailUnsubscribe::isUnsubscribed($normalized);
        if (! $wasBlocked) {
            $this->setEmailSubscription->handle($normalized, true);
        }

        return [
            'marked' => $marked,
            'already_bounced' => $alreadyBounced,
            'blocked' => true,
        ];
    }
}
