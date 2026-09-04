<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\Marketing\PromoCampaignDeliverySummaryData;
use App\Models\PromoCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PromoCampaign */
class PromoCampaignResource extends JsonResource
{
    public bool $includeContent = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = $this->deliverySummary ?? $this->resource->deliverySummary ?? null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'locale' => $this->locale,
            'landing' => $this->landing->value,
            'email_subject' => $this->email_subject,
            'email_plain' => (bool) $this->email_plain,
            'letter_body_html' => $this->when($this->includeContent, $this->letter_body_html),
            'email_body_html' => $this->when($this->includeContent, $this->email_body_html),
            'flow_image_path' => $this->flow_image_path,
            'youtube_url' => $this->youtube_url,
            'column_mapping' => $this->column_mapping,
            'emails_paused_at' => optional($this->emails_paused_at)->toIso8601String(),
            'emails_paused_reason' => $this->emails_paused_reason,
            'emails_paused_detail' => $this->emails_paused_detail,
            'targets_count' => $this->whenCounted('targets'),
            'delivery' => $this->when(
                $summary !== null,
                fn (): array => $this->deliveryPayload($summary),
            ),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryPayload(PromoCampaignDeliverySummaryData $summary): array
    {
        return [
            'status' => $summary->status->value,
            'targets' => $summary->targets,
            'with_email' => $summary->withEmail,
            'sent' => $summary->sent,
            'failed' => $summary->failed,
            'skipped' => $summary->skipped,
            'bounced' => $summary->bounced,
            'bounce_percent' => $summary->bouncePercent,
            'remaining' => $summary->remaining,
            'queued_jobs' => $summary->queuedJobs,
            'first_sent_at' => $summary->firstSentAt?->toIso8601String(),
            'last_sent_at' => $summary->lastSentAt?->toIso8601String(),
        ];
    }
}
