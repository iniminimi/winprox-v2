<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Data\Marketing\UpdatePromoCampaignData;
use App\Models\PromoCampaign;
use App\Support\Marketing\PromoCampaignQuillHtmlNormalizer;
use App\Support\Marketing\PromoCampaignYoutubeThumbnail;
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

        $letterBodyHtml = PromoCampaignQuillHtmlNormalizer::normalize($data->letterBodyHtml);
        $emailBodyHtml = PromoCampaignQuillHtmlNormalizer::normalize($data->emailBodyHtml);
        $youtubeUrl = $data->youtubeUrl !== null ? trim($data->youtubeUrl) : null;
        if ($youtubeUrl === '') {
            $youtubeUrl = null;
        }
        if ($youtubeUrl !== null && PromoCampaignYoutubeThumbnail::extractVideoId($youtubeUrl) === null) {
            throw new RuntimeException('Invalid YouTube URL.');
        }

        $campaign->update([
            'name' => $name,
            'locale' => $locale,
            'letter_body_html' => $letterBodyHtml !== '' ? $letterBodyHtml : null,
            'email_subject' => $data->emailSubject !== null ? trim($data->emailSubject) : null,
            'email_body_html' => $emailBodyHtml !== '' ? $emailBodyHtml : null,
            'attach_letter_to_email' => false,
            'flow_image_path' => $data->flowImagePath !== null && trim($data->flowImagePath) !== ''
                ? trim($data->flowImagePath)
                : null,
            'youtube_url' => $youtubeUrl,
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
