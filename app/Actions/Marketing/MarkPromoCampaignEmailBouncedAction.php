<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Actions\Contact\SetEmailSubscriptionAction;
use App\Models\EmailUnsubscribe;
use App\Models\PromoCampaignEmailSend;
use App\Models\PromoCampaignTarget;
use Illuminate\Support\Facades\DB;

class MarkPromoCampaignEmailBouncedAction
{
    public function __construct(
        private SetEmailSubscriptionAction $setEmailSubscription,
        private LogAuditAction $logAudit,
    ) {}

    /**
     * Block the address and remove matching promo campaign targets.
     *
     * @return array{removed: int, blocked: bool}
     */
    public function handle(string $recipientEmail, ?string $reason = null): array
    {
        $normalized = EmailUnsubscribe::normalizeEmail($recipientEmail);
        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            return ['removed' => 0, 'blocked' => false];
        }

        $reason = mb_substr(trim((string) ($reason ?: 'bounce')), 0, 1000);

        $wasBlocked = EmailUnsubscribe::isUnsubscribed($normalized);
        if (! $wasBlocked) {
            $this->setEmailSubscription->handle($normalized, true);
        }

        $targetIds = PromoCampaignEmailSend::query()
            ->whereRaw('LOWER(recipient_email) = ?', [$normalized])
            ->pluck('promo_campaign_target_id')
            ->all();

        $targetsByEmail = PromoCampaignTarget::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->pluck('id')
            ->all();

        $targetIds = array_values(array_unique(array_merge(
            array_map('intval', $targetIds),
            array_map('intval', $targetsByEmail),
        )));

        if ($targetIds === []) {
            return ['removed' => 0, 'blocked' => true];
        }

        $targets = PromoCampaignTarget::query()
            ->whereIn('id', $targetIds)
            ->get();

        $removed = 0;

        DB::transaction(function () use ($targets, $normalized, $reason, &$removed): void {
            foreach ($targets as $target) {
                $this->logAudit->handle(
                    userId: null,
                    tenantId: null,
                    action: 'marketing.promo_campaign_email_bounced',
                    modelType: 'PromoCampaignTarget',
                    modelId: $target->id,
                    payload: [
                        'promo_campaign_id' => $target->promo_campaign_id,
                        'promo_campaign_target_id' => $target->id,
                        'recipient_email' => $normalized,
                        'target_name' => $target->name,
                        'reason' => $reason,
                        'removed' => true,
                    ],
                );

                $this->deleteLetterFile($target);
                $target->delete();
                $removed++;
            }
        });

        return [
            'removed' => $removed,
            'blocked' => true,
        ];
    }

    private function deleteLetterFile(PromoCampaignTarget $target): void
    {
        if ($target->docx_filename === null || $target->docx_filename === '') {
            return;
        }

        $campaign = $target->campaign;
        if ($campaign === null) {
            return;
        }

        $path = $campaign->lettersDirectory().DIRECTORY_SEPARATOR.$target->docx_filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
