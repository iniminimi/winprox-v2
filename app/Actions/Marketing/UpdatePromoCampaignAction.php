<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Data\Marketing\UpdatePromoCampaignData;
use App\Models\PromoCampaign;
use App\Support\Marketing\PromoCampaignHtmlSanitizer;
use RuntimeException;

class UpdatePromoCampaignAction
{
    public function __construct(private LogAuditAction $logAudit) {}

    public function handle(PromoCampaign $campaign, UpdatePromoCampaignData $data, int $actorUserId): PromoCampaign
    {
        $name = trim($data->name);
        $locale = strtolower(trim($data->locale));
        if ($name === '' || $locale === '') {
            throw new RuntimeException('Name and locale are required.');
        }

        $columnMapping = $data->columnMapping;
        if ($columnMapping !== null) {
            $columnMapping = array_filter(
                $columnMapping,
                static fn (string $value): bool => trim($value) !== '',
            );
            if ($columnMapping === []) {
                $columnMapping = null;
            }
        }

        $campaign->update([
            'name' => $name,
            'locale' => $locale,
            'letter_body_html' => PromoCampaignHtmlSanitizer::clean($data->letterBodyHtml),
            'email_subject' => $data->emailSubject !== null ? trim($data->emailSubject) : null,
            'email_body_html' => PromoCampaignHtmlSanitizer::clean($data->emailBodyHtml),
            'flow_image_path' => $data->flowImagePath !== null && trim($data->flowImagePath) !== ''
                ? trim($data->flowImagePath)
                : null,
            'column_mapping' => $columnMapping,
        ]);

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_campaign_updated',
            modelType: 'PromoCampaign',
            modelId: $campaign->id,
            payload: [
                'slug' => $campaign->slug,
            ],
        );

        return $campaign->fresh() ?? $campaign;
    }
}
