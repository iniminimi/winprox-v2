<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Models\PromoCampaign;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreatePromoCampaignAction
{
    public function __construct(private LogAuditAction $logAudit) {}

    public function handle(string $slug, string $name, string $locale, int $actorUserId): PromoCampaign
    {
        $slug = strtolower(trim($slug));
        $name = trim($name);
        $locale = strtolower(trim($locale));

        if ($slug === '' || $name === '' || $locale === '') {
            throw new RuntimeException('Slug, name and locale are required.');
        }

        $campaign = DB::transaction(function () use ($slug, $name, $locale, $actorUserId): PromoCampaign {
            return PromoCampaign::query()->create([
                'slug' => $slug,
                'name' => $name,
                'locale' => $locale,
                'letter_body_html' => null,
                'email_subject' => null,
                'email_body_html' => null,
                'flow_image_path' => null,
                'column_mapping' => null,
                'created_by' => $actorUserId,
            ]);
        });

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_campaign_created',
            modelType: 'PromoCampaign',
            modelId: $campaign->id,
            payload: [
                'slug' => $campaign->slug,
                'name' => $campaign->name,
                'locale' => $campaign->locale,
            ],
        );

        return $campaign;
    }
}
