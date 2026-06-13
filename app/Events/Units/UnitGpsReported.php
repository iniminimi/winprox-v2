<?php

namespace App\Events\Units;

use App\Contracts\WebhookEvent;
use App\Models\UnitGpsReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnitGpsReported implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly UnitGpsReport $report,
        public readonly ?int $actorUserId = null,
    ) {
    }

    public function webhookEventName(): string
    {
        return 'unit.gps_reported';
    }

    public function webhookPayload(): array
    {
        $payload = [
            'id' => $this->report->id,
            'unit_id' => $this->report->unit_id,
            'latitude' => $this->report->latitude,
            'longitude' => $this->report->longitude,
            'reported_at' => $this->report->reported_at->toIso8601String(),
            'worker_id' => $this->report->worker_id,
        ];

        if ($this->actorUserId !== null) {
            $payload['actor_user_id'] = $this->actorUserId;
            $payload['user_id'] = $this->actorUserId;
        }

        return $payload;
    }

    public function webhookTenantId(): int
    {
        return (int) $this->report->tenant_id;
    }
}
