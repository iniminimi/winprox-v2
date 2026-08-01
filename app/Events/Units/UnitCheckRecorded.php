<?php

declare(strict_types=1);

namespace App\Events\Units;

use App\Contracts\WebhookEvent;
use App\Models\UnitCheck;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnitCheckRecorded implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly UnitCheck $check,
        public readonly ?int $actorUserId = null,
    ) {}

    public function webhookEventName(): string
    {
        return 'unit.check.recorded';
    }

    public function webhookPayload(): array
    {
        $this->check->loadMissing('unit');

        $payload = [
            'id' => $this->check->id,
            'unit_id' => $this->check->unit_id,
            'location_id' => $this->check->location_id,
            'worker_id' => $this->check->worker_id,
            'internal_team_id' => $this->check->internal_team_id,
            'result' => $this->check->result->value,
            'source' => $this->check->source->value,
            'checked_at' => $this->check->checked_at->toIso8601String(),
            'latitude' => $this->check->latitude,
            'longitude' => $this->check->longitude,
            'task_id' => $this->check->task_id,
            'issue_id' => $this->check->issue_id,
            'checklist_items' => $this->check->checklist_items,
            'external_id' => $this->check->external_id,
            'unit_external_id' => $this->check->unit?->external_id,
            'google_maps_url' => $this->check->googleMapsUrl(),
        ];

        if ($this->actorUserId !== null) {
            $payload['actor_user_id'] = $this->actorUserId;
            $payload['user_id'] = $this->actorUserId;
        }

        return $payload;
    }

    public function webhookTenantId(): int
    {
        return (int) $this->check->tenant_id;
    }
}
