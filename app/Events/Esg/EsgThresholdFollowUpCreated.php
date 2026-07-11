<?php

declare(strict_types=1);

namespace App\Events\Esg;

use App\Contracts\WebhookEvent;
use App\Models\EsgMeasurement;
use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EsgThresholdFollowUpCreated implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Task $task,
        public readonly EsgMeasurement $measurement,
        public readonly ?int $actorUserId = null,
    ) {}

    public function webhookEventName(): string
    {
        return 'esg.threshold.follow_up_created';
    }

    public function webhookPayload(): array
    {
        $payload = [
            'task_id' => $this->task->id,
            'issue_id' => $this->task->issue_id,
            'esg_measurement_id' => $this->measurement->id,
            'esg_indicator_id' => $this->measurement->esg_indicator_id,
            'unit_id' => $this->measurement->unit_id,
            'location_id' => $this->measurement->location_id,
            'recorded_at' => $this->measurement->recorded_at?->toIso8601String(),
            'value_numeric' => $this->measurement->value_numeric !== null
                ? (float) $this->measurement->value_numeric
                : null,
        ];

        if ($this->actorUserId !== null) {
            $payload['actor_user_id'] = $this->actorUserId;
        }

        return $payload;
    }

    public function webhookTenantId(): int
    {
        return (int) $this->task->tenant_id;
    }
}
