<?php

namespace App\Events\Tasks;

use App\Contracts\WebhookEvent;
use App\Models\TaskTranslation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskTranslationImported implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TaskTranslation $translation,
        public ?int $actorUserId = null,
    ) {}

    public function webhookEventName(): string
    {
        return 'task.translation_imported';
    }

    public function webhookPayload(): array
    {
        return [
            'task_id' => $this->translation->task_id,
            'locale' => $this->translation->locale,
            'status' => $this->translation->status->value,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function webhookTenantId(): int
    {
        return (int) $this->translation->task->tenant_id;
    }
}
