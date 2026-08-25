<?php

namespace App\Http\Resources;

use App\Enums\IssueSource;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Issue */
class IssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'description' => $this->localizedDescription(),
            'original_language' => $this->normalizedOriginalLanguage(),
            'translations' => $this->whenLoaded(
                'translations',
                fn () => $this->completedTranslationMap(),
            ),
            'location_id' => $this->location_id,
            'unit_id' => $this->unit_id,
            'reporter_name' => $this->reporter_name,
            'reporter_contact' => $this->reporter_contact,
            'reporter_email_verified' => (bool) $this->reporter_email_verified,
            'approved' => $this->isApproved(),
            'approved_at' => optional($this->approved_at)->toIso8601String(),
            'source' => $this->source?->value ?? IssueSource::Manager->value,
            'is_recurring' => (bool) $this->is_recurring,
            'esg_indicator_id' => $this->esg_indicator_id,
            'recurrence_next_due_at' => optional($this->recurrence_next_due_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'tasks' => $this->isApproved()
                ? TaskResource::collection($this->whenLoaded('tasks'))
                : [],
        ];
    }
}
