<?php

namespace App\Events\Issues;

use App\Contracts\WebhookEvent;
use App\Models\Issue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueCreated implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public Issue $issue, public ?int $actorUserId = null) {}

    public function webhookEventName(): string
    {
        return 'issue.created';
    }

    public function webhookPayload(): array
    {
        $payload = [
            'id' => $this->issue->id,
            'status' => $this->issue->status->value,
            'location_id' => $this->issue->location_id,
            'unit_id' => $this->issue->unit_id,
            'approved' => $this->issue->isApproved(),
            'created_at' => optional($this->issue->created_at)->toIso8601String(),
        ];

        if ($this->actorUserId !== null) {
            $payload['actor_user_id'] = $this->actorUserId;
            $payload['user_id'] = $this->actorUserId;
        }

        return $payload;
    }

    public function webhookTenantId(): int
    {
        return (int) $this->issue->tenant_id;
    }
}
