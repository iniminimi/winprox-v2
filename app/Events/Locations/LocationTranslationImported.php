<?php

namespace App\Events\Locations;

use App\Contracts\WebhookEvent;
use App\Models\LocationTranslation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationTranslationImported implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LocationTranslation $translation,
        public ?int $actorUserId = null,
    ) {}

    public function webhookEventName(): string
    {
        return 'location.translation_imported';
    }

    public function webhookPayload(): array
    {
        return [
            'location_id' => $this->translation->location_id,
            'locale' => $this->translation->locale,
            'status' => $this->translation->status->value,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->translation->location->tenant_id;
    }
}
