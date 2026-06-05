<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

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

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()
            ->withoutGlobalScope('tenant')
            ->with(['endpoint' => fn ($q) => $q->withoutGlobalScope('tenant')])
            ->find($this->deliveryId);

        if ($delivery === null || $delivery->endpoint === null) {
            return;
        }

        $tenant = Tenant::query()->find($delivery->tenant_id);
        
        if ($tenant === null || ! $tenant->hasApiAccess()) {
            $delivery->forceFill([
                'status' => WebhookDelivery::STATUS_FAILED,
                'error' => 'API access revoked or not available',
                'attempts' => $delivery->attempts + 1,
            ])->save();
            return;
        }

        $endpoint = $delivery->endpoint;
        $body = json_encode([
            'event' => $delivery->event,
            'payload' => $delivery->payload,
            'delivery_id' => $delivery->id,
        ], JSON_THROW_ON_ERROR);

        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);

        $delivery->forceFill([
            'attempts' => $delivery->attempts + 1,
            'dispatched_at' => $delivery->dispatched_at ?? now(),
        ])->save();

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-WinProx-Event' => $delivery->event,
                    'X-WinProx-Delivery' => (string) $delivery->id,
                    'X-WinProx-Timestamp' => $timestamp,
                    'X-WinProx-Signature' => 'sha256='.$signature,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => WebhookDelivery::STATUS_SUCCESS,
                    'response_status' => $response->status(),
                    'delivered_at' => now(),
                    'error' => null,
                ])->save();

                return;
            }

            $delivery->forceFill([
                'response_status' => $response->status(),
                'error' => 'HTTP '.$response->status(),
            ])->save();
        } catch (\Throwable $e) {
            $delivery->forceFill([
                'error' => $e->getMessage(),
            ])->save();
        }

        if ($this->attempts() >= $this->tries) {
            $delivery->forceFill(['status' => WebhookDelivery::STATUS_FAILED])->save();
        }

        throw new \RuntimeException('Webhook delivery failed');
    }
}
