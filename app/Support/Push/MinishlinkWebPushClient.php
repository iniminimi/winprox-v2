<?php

namespace App\Support\Push;

use App\Contracts\WebPushClient;
use App\Data\Notifications\WebPushResult;
use GuzzleHttp\Client;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Praat met Apple, Google en Mozilla. Geen eigen push-server.
 */
class MinishlinkWebPushClient implements WebPushClient
{
    public function send(string $endpoint, string $publicKey, string $authToken, string $payload): WebPushResult
    {
        $vapidPublic = (string) config('push.public_key');
        $vapidPrivate = (string) config('push.private_key');
        if ($vapidPublic === '' || $vapidPrivate === '') {
            return WebPushResult::failed();
        }

        try {
            $webPush = new WebPush(
                [
                    'VAPID' => [
                        'subject' => (string) config('push.subject'),
                        'publicKey' => $vapidPublic,
                        'privateKey' => $vapidPrivate,
                    ],
                ],
                ['TTL' => 86400, 'urgency' => 'normal'],
                new Client(['timeout' => 10, 'connect_timeout' => 5]),
            );

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $endpoint,
                    'publicKey' => $publicKey,
                    'authToken' => $authToken,
                    'contentEncoding' => 'aes128gcm',
                ]),
                $payload,
            );
        } catch (Throwable) {
            return WebPushResult::failed();
        }

        if ($report->isSubscriptionExpired()) {
            return WebPushResult::gone();
        }

        return $report->isSuccess() ? WebPushResult::delivered() : WebPushResult::failed();
    }
}
