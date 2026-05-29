<?php

namespace App\Events\Tasks;

use App\Contracts\WebhookEvent;
use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCreated implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public Task $task) {}

    public function webhookEventName(): string
    {
        return 'task.created';
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->task->id,
            'issue_id' => $this->task->issue_id,
            'internal_team_id' => $this->task->internal_team_id,
            'status' => $this->task->status->value,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->task->tenant_id;
    }
}
