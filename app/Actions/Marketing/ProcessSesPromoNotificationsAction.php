<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\EmailUnsubscribe;
use App\Support\Marketing\PromoBounceMessageParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessSesPromoNotificationsAction
{
    public function __construct(private MarkPromoCampaignEmailBouncedAction $markBounced) {}

    /**
     * Handle an Amazon SNS payload for SES bounce/complaint events.
     *
     * @param  array<string, mixed>  $payload
     * @return array{type: string, processed: int}
     */
    public function handle(array $payload): array
    {
        $snsType = (string) ($payload['Type'] ?? '');

        if ($snsType === 'SubscriptionConfirmation') {
            $this->confirmSubscription((string) ($payload['SubscribeURL'] ?? ''));

            return ['type' => 'subscription_confirmation', 'processed' => 0];
        }

        if ($snsType === 'UnsubscribeConfirmation') {
            return ['type' => 'unsubscribe_confirmation', 'processed' => 0];
        }

        if ($snsType !== 'Notification') {
            return ['type' => 'ignored', 'processed' => 0];
        }

        $message = $payload['Message'] ?? [];
        if (is_string($message)) {
            $decoded = json_decode($message, true);
            $message = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($message)) {
            return ['type' => 'ignored', 'processed' => 0];
        }

        $notificationType = (string) ($message['notificationType'] ?? $message['eventType'] ?? '');

        if ($notificationType === 'Bounce') {
            $bounceType = (string) ($message['bounce']['bounceType'] ?? '');
            if (strcasecmp($bounceType, 'Permanent') !== 0) {
                return ['type' => 'transient_bounce', 'processed' => 0];
            }

            return [
                'type' => 'bounce',
                'processed' => $this->markEmails(
                    $this->emailsFrom($message['bounce']['bouncedRecipients'] ?? []),
                    'ses_bounce',
                ),
            ];
        }

        if ($notificationType === 'Complaint') {
            return [
                'type' => 'complaint',
                'processed' => $this->markEmails(
                    $this->emailsFrom($message['complaint']['complainedRecipients'] ?? []),
                    'ses_complaint',
                ),
            ];
        }

        return ['type' => 'ignored', 'processed' => 0];
    }

    /**
     * @param  list<mixed>  $recipients
     * @return list<string>
     */
    private function emailsFrom(array $recipients): array
    {
        $emails = [];
        foreach ($recipients as $recipient) {
            $raw = is_array($recipient) ? (string) ($recipient['emailAddress'] ?? '') : (string) $recipient;
            if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $matches) !== 1) {
                continue;
            }
            $normalized = EmailUnsubscribe::normalizeEmail($matches[0]);
            if ($normalized !== '' && PromoBounceMessageParser::isPlausibleRecipientEmail($normalized)) {
                $emails[] = $normalized;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @param  list<string>  $emails
     */
    private function markEmails(array $emails, string $reason): int
    {
        $processed = 0;
        foreach ($emails as $email) {
            $this->markBounced->handle($email, $reason);
            $processed++;
        }

        return $processed;
    }

    private function confirmSubscription(string $subscribeUrl): void
    {
        $subscribeUrl = trim($subscribeUrl);
        if ($subscribeUrl === '' || ! $this->isAmazonSubscribeUrl($subscribeUrl)) {
            Log::warning('ses_sns_subscribe_url_rejected', ['url' => $subscribeUrl]);

            return;
        }

        $response = Http::timeout(10)->get($subscribeUrl);
        if (! $response->successful()) {
            Log::warning('ses_sns_subscribe_confirm_failed', [
                'status' => $response->status(),
            ]);
        }
    }

    private function isAmazonSubscribeUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return $host === 'sns.amazonaws.com' || str_ends_with($host, '.amazonaws.com');
    }
}
