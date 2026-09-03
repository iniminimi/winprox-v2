<?php

namespace App\Events\Time;

use App\Contracts\WebhookEvent;
use App\Models\PresenceSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PresenceSubmissionSubmitted implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public PresenceSubmission $submission) {}

    public function webhookEventName(): string
    {
        return 'time.presence.submitted';
    }

    public function webhookPayload(): array
    {
        return PresenceSubmissionWebhookPayload::from($this->submission);
    }

    public function webhookTenantId(): int
    {
        return (int) $this->submission->tenant_id;
    }
}
