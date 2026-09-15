<?php

namespace App\Events\Time;

use App\Contracts\WebhookEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduleSaved implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<int>  $workerIds
     * @param  list<string>  $dates
     */
    public function __construct(
        public int $tenantId,
        public ?int $actorUserId,
        public string $weekStart,
        public array $workerIds,
        public array $dates,
        public int $count,
    ) {}

    public function webhookEventName(): string
    {
        return 'time.schedule.saved';
    }

    public function webhookPayload(): array
    {
        return [
            'week_start' => $this->weekStart,
            'worker_ids' => $this->workerIds,
            'dates' => $this->dates,
            'count' => $this->count,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function webhookTenantId(): int
    {
        return $this->tenantId;
    }
}
