<?php

namespace App\Events\Issues;

use App\Contracts\WebhookEvent;
use App\Models\Issue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueApproved implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public Issue $issue) {}

    public function webhookEventName(): string
    {
        return 'issue.approved';
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->issue->id,
            'status' => $this->issue->status->value,
            'approved_at' => optional($this->issue->approved_at)->toIso8601String(),
            'approved_by' => $this->issue->approved_by,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->issue->tenant_id;
    }
}
