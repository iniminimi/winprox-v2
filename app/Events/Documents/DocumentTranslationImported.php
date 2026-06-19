<?php

namespace App\Events\Documents;

use App\Contracts\WebhookEvent;
use App\Models\DocumentTranslation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentTranslationImported implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DocumentTranslation $translation,
        public ?int $actorUserId = null,
    ) {}

    public function webhookEventName(): string
    {
        return 'document.translation_imported';
    }

    public function webhookPayload(): array
    {
        return [
            'document_id' => $this->translation->document_id,
            'locale' => $this->translation->locale,
            'status' => $this->translation->status->value,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->translation->document->tenant_id;
    }
}
