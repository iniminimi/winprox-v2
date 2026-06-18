<?php

namespace App\Events\Communication;

use App\Contracts\WebhookEvent;
use App\Models\AnnouncementTranslation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementTranslationImported implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AnnouncementTranslation $translation,
        public ?int $actorUserId = null,
    ) {}

    public function webhookEventName(): string
    {
        return 'announcement.translation_imported';
    }

    public function webhookPayload(): array
    {
        return [
            'announcement_id' => $this->translation->announcement_id,
            'locale' => $this->translation->locale,
            'status' => $this->translation->status->value,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->translation->announcement->tenant_id;
    }
}
