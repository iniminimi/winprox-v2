<?php

namespace App\Events\Time;

use App\Contracts\WebhookEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AbsenceApproved implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $replacedShifts
     */
    public function __construct(
        public int $tenantId,
        public int $id,
        public int $workerId,
        public string $kind,
        public string $dateFrom,
        public string $dateTo,
        public string $reason,
        public ?int $actorUserId,
        public array $replacedShifts,
    ) {}

    public function webhookEventName(): string
    {
        return 'time.absence.approved';
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->id,
            'worker_id' => $this->workerId,
            'kind' => $this->kind,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'reason' => $this->reason,
            'replaced_shifts' => $this->replacedShifts,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function webhookTenantId(): int
    {
        return $this->tenantId;
    }
}
