<?php

namespace App\Events\Contact;

use App\Contracts\WebhookEvent;
use App\Models\ContactMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactReplySent implements WebhookEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ContactMessage $originalMessage,
        public readonly ContactMessage $replyMessage,
        public readonly ?int $actorUserId,
    ) {
    }

    public function webhookEventName(): string
    {
        return 'contact_reply_sent';
    }

    public function webhookPayload(): array
    {
        return [
            'original_message_id' => $this->originalMessage->id,
            'reply_message_id' => $this->replyMessage->id,
            'recipient_email' => $this->originalMessage->email,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function webhookTenantId(): int
    {
        return $this->replyMessage->tenant_id ?? 0;
    }
}
