<?php

namespace App\Events\Units;

use App\Contracts\WebhookEvent;
use App\Models\UnitTranslation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnitTranslationImported implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public UnitTranslation $translation,
        public ?int $actorUserId = null,
    ) {}

    public function webhookEventName(): string
    {
        return 'unit.translation_imported';
    }

    public function webhookPayload(): array
    {
        return [
            'unit_id' => $this->translation->unit_id,
            'locale' => $this->translation->locale,
            'status' => $this->translation->status->value,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->translation->unit->tenant_id;
    }
}
