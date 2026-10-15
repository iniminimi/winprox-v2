<?php

namespace App\Actions\Webhooks;

use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Support\Webhook\PublicUrlGuard;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class DeliverWebhookAction
{
    /**
     * @return array{success: bool, should_retry: bool}
     */
    public function handle(WebhookDelivery $delivery, int $attemptNumber, int $maxAttempts): array
    {
        if ($delivery->endpoint === null) {
            return ['success' => false, 'should_retry' => false];
        }

        $tenant = Tenant::query()->find($delivery->tenant_id);

        if ($tenant === null || ! $tenant->hasApiAccess()) {
            $delivery->forceFill([
                'status' => WebhookDelivery::STATUS_FAILED,
                'error' => 'API access revoked or not available',
                'attempts' => $delivery->attempts + 1,
            ])->save();

            return ['success' => false, 'should_retry' => false];
        }

        $endpoint = $delivery->endpoint;

        // CURLOPT_RESOLVE werkt alleen met de curl-handler. Zonder curl-extensie
        // valt Guzzle terug op de stream-handler en doet de pin niets.
        if (! extension_loaded('curl')) {
            $delivery->forceFill([
                'status' => WebhookDelivery::STATUS_FAILED,
                'error' => 'webhook_curl_required',
                'attempts' => $delivery->attempts + 1,
            ])->save();

            return ['success' => false, 'should_retry' => false];
        }

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
            $target = PublicUrlGuard::deliveryOptions($endpoint->url);

            $response = Http::withOptions($target['options'])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-WinProx-Event' => $delivery->event,
                    'X-WinProx-Delivery' => (string) $delivery->id,
                    'X-WinProx-Timestamp' => $timestamp,
                    'X-WinProx-Signature' => 'sha256='.$signature,
                ])
                ->withBody($body, 'application/json')
                ->post($target['url']);

            $status = $response->status();
            $response->close();

            if ($status >= 200 && $status < 300) {
                $delivery->forceFill([
                    'status' => WebhookDelivery::STATUS_SUCCESS,
                    'response_status' => $status,
                    'delivered_at' => now(),
                    'error' => null,
                ])->save();

                return ['success' => true, 'should_retry' => false];
            }

            $delivery->forceFill([
                'response_status' => $status,
                'error' => 'HTTP '.$status,
            ])->save();
        } catch (InvalidArgumentException $e) {
            $delivery->forceFill([
                'status' => WebhookDelivery::STATUS_FAILED,
                'error' => $e->getMessage(),
            ])->save();

            return ['success' => false, 'should_retry' => false];
        } catch (\Throwable $e) {
            $delivery->forceFill([
                'error' => $e->getMessage(),
            ])->save();
        }

        if ($attemptNumber >= $maxAttempts) {
            $delivery->forceFill(['status' => WebhookDelivery::STATUS_FAILED])->save();
        }

        return ['success' => false, 'should_retry' => $attemptNumber < $maxAttempts];
    }
}
