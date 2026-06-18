<?php

namespace App\Events\Issues;

use App\Contracts\WebhookEvent;
use App\Models\IssueTranslation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueTranslationImported implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public IssueTranslation $translation,
        public ?int $actorUserId = null,
    ) {}

    public function webhookEventName(): string
    {
        return 'issue.translation_imported';
    }

    public function webhookPayload(): array
    {
        return [
            'issue_id' => $this->translation->issue_id,
            'locale' => $this->translation->locale,
            'status' => $this->translation->status->value,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->translation->issue->tenant_id;
    }
}
