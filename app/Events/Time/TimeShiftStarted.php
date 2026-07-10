<?php

namespace App\Events\Time;

use App\Contracts\WebhookEvent;
use App\Models\WorkShift;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimeShiftStarted implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public WorkShift $shift, public ?int $workerId = null) {}

    public function webhookEventName(): string
    {
        return 'time.shift.started';
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->shift->id,
            'worker_id' => $this->shift->worker_id,
            'internal_team_id' => $this->shift->internal_team_id,
            'clock_in_clock_point_id' => $this->shift->clock_in_clock_point_id,
            'clock_in_at' => $this->shift->clock_in_at->toIso8601String(),
            'status' => $this->shift->status->value,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->shift->tenant_id;
    }
}
