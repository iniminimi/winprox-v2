<?php

namespace App\Jobs;

use App\Actions\Webhooks\DeliverWebhookAction;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWebhookDeliveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function __construct(public int $deliveryId) {}

    public function handle(DeliverWebhookAction $deliverWebhook): void
    {
        $delivery = WebhookDelivery::query()
            ->withoutGlobalScope('tenant')
            ->with(['endpoint' => fn ($q) => $q->withoutGlobalScope('tenant')])
            ->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $result = $deliverWebhook->handle($delivery, $this->attempts(), $this->tries);

        if (! $result['success'] && $result['should_retry']) {
            throw new \RuntimeException('Webhook delivery failed');
        }
    }
}
