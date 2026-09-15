<?php

namespace App\Events\Time;

use App\Contracts\WebhookEvent;
use App\Models\ShiftType;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShiftTypeSaved implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public ShiftType $shiftType, public ?int $actorUserId = null) {}

    public function webhookEventName(): string
    {
        return 'time.shift_type.saved';
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->shiftType->id,
            'code' => $this->shiftType->code,
            'label' => $this->shiftType->label,
            'kind' => $this->shiftType->kind->value,
            'start_time' => $this->shiftType->start_time,
            'end_time' => $this->shiftType->end_time,
            'break_minutes' => $this->shiftType->break_minutes,
            'color' => $this->shiftType->color->value,
            'is_active' => $this->shiftType->is_active,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->shiftType->tenant_id;
    }
}
