<?php

namespace App\Events\Time;

use App\Contracts\WebhookEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AbsenceRequested implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $id,
        public int $workerId,
        public string $kind,
        public string $dateFrom,
        public string $dateTo,
    ) {}

    public function webhookEventName(): string
    {
        return 'time.absence.requested';
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->id,
            'worker_id' => $this->workerId,
            'kind' => $this->kind,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }

    public function webhookTenantId(): int
    {
        return $this->tenantId;
    }
}
