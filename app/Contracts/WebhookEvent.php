<?php

namespace App\Contracts;

/**
 * Domein-events die naar webhooks vertaald worden, implementeren dit contract.
 * Eén listener (DispatchWebhooksForDomainEvent) leest hieruit en roept de
 * DispatchWebhookEventAction aan — integration-first, geen UI-afhankelijkheid.
 */
interface WebhookEvent
{
    /** Stabiele event-naam, bv. "issue.created". */
    public function webhookEventName(): string;

    /**
     * Minimale, stabiele payload van het event.
     *
     * @return array<string, mixed>
     */
    public function webhookPayload(): array;

    /** Tenant waartoe de entiteit behoort (expliciete scope). */
    public function webhookTenantId(): int;
}
