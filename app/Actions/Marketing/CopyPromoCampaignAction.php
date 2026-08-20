<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Models\PromoCampaign;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CopyPromoCampaignAction
{
    public function __construct(private LogAuditAction $logAudit) {}

    public function handle(
        PromoCampaign $source,
        string $slug,
        string $name,
        string $locale,
        int $actorUserId,
    ): PromoCampaign {
        $slug = strtolower(trim($slug));
        $name = trim($name);
        $locale = strtolower(trim($locale));

        if ($slug === '' || $name === '' || $locale === '') {
            throw new RuntimeException('Slug, name and locale are required.');
        }

        $campaign = DB::transaction(function () use ($source, $slug, $name, $locale, $actorUserId): PromoCampaign {
            return PromoCampaign::query()->create([
                'slug' => $slug,
                'name' => $name,
                'locale' => $locale,
                'letter_body_html' => $source->letter_body_html,
                'email_subject' => $source->email_subject,
                'email_body_html' => $source->email_body_html,
                'attach_letter_to_email' => $source->attach_letter_to_email,
                'flow_image_path' => $source->flow_image_path,
                'youtube_url' => $source->youtube_url,
                'column_mapping' => $source->column_mapping,
                'created_by' => $actorUserId,
            ]);
        });

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_campaign_copied',
            modelType: 'PromoCampaign',
            modelId: $campaign->id,
            payload: [
                'source_id' => $source->id,
                'source_slug' => $source->slug,
                'slug' => $campaign->slug,
                'name' => $campaign->name,
                'locale' => $campaign->locale,
            ],
        );

        return $campaign;
    }
}
