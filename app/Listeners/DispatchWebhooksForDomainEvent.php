<?php

namespace App\Listeners;

use App\Actions\Webhooks\DispatchWebhookEventAction;
use App\Contracts\WebhookEvent;

/**
 * Vertaalt domein-events (WebhookEvent-contract) naar uitgaande webhook-leveringen.
 */
class DispatchWebhooksForDomainEvent
{
    public function __construct(private DispatchWebhookEventAction $dispatch) {}

    public function handle(WebhookEvent $event): void
    {
        $this->dispatch->handle(
            $event->webhookEventName(),
            $event->webhookPayload(),
            $event->webhookTenantId(),
        );
    }
}
