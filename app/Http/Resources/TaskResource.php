<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'issue_id' => $this->issue_id,
            'internal_team_id' => $this->internal_team_id,
            'status' => $this->status->value,
            'description' => $this->description,
            'scheduled_for' => $this->scheduled_for?->format('Y-m-d'),
            'due_at' => optional($this->due_at)->toIso8601String(),
            'is_recurring_cycle' => (bool) $this->is_recurring_cycle,
            'cycle_number' => $this->cycle_number,
            'started_at' => optional($this->started_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
