<?php

namespace App\Events\Time;

use App\Contracts\WebhookEvent;
use App\Models\WorkVisit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkVisitStarted implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public WorkVisit $visit) {}

    public function webhookEventName(): string
    {
        return 'time.visit.started';
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->visit->id,
            'work_shift_id' => $this->visit->work_shift_id,
            'worker_id' => $this->visit->worker_id,
            'unit_id' => $this->visit->unit_id,
            'location_id' => $this->visit->location_id,
            'started_at' => $this->visit->started_at->toIso8601String(),
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->visit->tenant_id;
    }
}
