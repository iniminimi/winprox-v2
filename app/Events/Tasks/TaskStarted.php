<?php

namespace App\Events\Tasks;

use App\Contracts\WebhookEvent;
use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStarted implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public Task $task) {}

    public function webhookEventName(): string
    {
        return 'task.started';
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->task->id,
            'issue_id' => $this->task->issue_id,
            'status' => $this->task->status->value,
            'started_at' => optional($this->task->started_at)->toIso8601String(),
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->task->tenant_id;
    }
}
