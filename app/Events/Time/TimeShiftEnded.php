<?php

namespace App\Events\Time;

use App\Contracts\WebhookEvent;
use App\Models\WorkShift;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimeShiftEnded implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WorkShift $shift,
        public ?int $actorUserId = null,
    ) {}

    public function webhookEventName(): string
    {
        return 'time.shift.ended';
    }

    public function webhookPayload(): array
    {
        $payload = [
            'id' => $this->shift->id,
            'worker_id' => $this->shift->worker_id,
            'internal_team_id' => $this->shift->internal_team_id,
            'clock_in_clock_point_id' => $this->shift->clock_in_clock_point_id,
            'clock_out_clock_point_id' => $this->shift->clock_out_clock_point_id,
            'clock_in_at' => $this->shift->clock_in_at->toIso8601String(),
            'clock_out_at' => optional($this->shift->clock_out_at)->toIso8601String(),
            'total_break_minutes' => $this->shift->total_break_minutes,
            'net_work_minutes' => $this->shift->netWorkMinutes(),
            'status' => $this->shift->status->value,
        ];

        if ($this->actorUserId !== null) {
            $payload['actor_user_id'] = $this->actorUserId;
            $payload['user_id'] = $this->actorUserId;
        }

        return $payload;
    }

    public function webhookTenantId(): int
    {
        return (int) $this->shift->tenant_id;
    }
}
