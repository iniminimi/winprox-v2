<?php

namespace App\Events\Issues;

use App\Contracts\WebhookEvent;
use App\Enums\TaskStatus;
use App\Models\Issue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueStatusChanged implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Issue $issue,
        public TaskStatus $previousStatus,
    ) {}

    public function webhookEventName(): string
    {
        return 'issue.status_changed';
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->issue->id,
            'status' => $this->issue->status->value,
            'previous_status' => $this->previousStatus->value,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->issue->tenant_id;
    }
}
