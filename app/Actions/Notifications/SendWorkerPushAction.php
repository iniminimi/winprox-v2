<?php

namespace App\Actions\Notifications;

use App\Contracts\WebPushClient;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Audit\AuditRecorder;

class SendWorkerPushAction
{
    public const SENT = 'sent';

    public const SKIPPED = 'skipped';

    public const GONE = 'gone';

    public const FAILED = 'failed';

    public function __construct(
        private WebPushClient $client,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, string>  $replace
     */
    public function handle(
        int $tenantId,
        int $workerId,
        string $titleKey,
        string $bodyKey,
        array $replace,
        string $url,
        string $tag,
    ): string {
        $worker = Worker::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereKey($workerId)
            ->first();

        if (! $worker instanceof Worker || ! $worker->is_active || $worker->clock_device_id === null) {
            return self::SKIPPED;
        }

        $device = WorkerDevice::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->whereKey($worker->clock_device_id)
            ->first();

        if (! $device instanceof WorkerDevice || ! is_string($device->push_endpoint) || $device->push_endpoint === '') {
            return self::SKIPPED;
        }

        if ((string) config('push.public_key') === '' || (string) config('push.private_key') === '') {
            return self::SKIPPED;
        }

        $locale = $this->locale($device->push_locale);
        $payload = json_encode([
            'title' => __($titleKey, $replace, $locale),
            'body' => __($bodyKey, $replace, $locale),
            'url' => $url,
            'tag' => $tag,
        ], JSON_UNESCAPED_UNICODE);

        if (! is_string($payload)) {
            return self::FAILED;
        }

        $result = $this->client->send(
            $device->push_endpoint,
            (string) $device->push_public_key,
            (string) $device->push_auth_token,
            $payload,
        );

        if ($result->gone) {
            $this->clear($device, $tenantId, (int) $worker->id);

            return self::GONE;
        }

        if ($result->delivered) {
            return self::SENT;
        }

        return self::FAILED;
    }

    private function locale(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));
        $allowed = config('locales.portal');
        if (is_array($allowed) && in_array($locale, $allowed, true)) {
            return $locale;
        }

        return (string) config('app.locale', 'nl');
    }

    private function clear(WorkerDevice $device, int $tenantId, int $workerId): void
    {
        $device->forceFill([
            'push_endpoint' => null,
            'push_endpoint_hash' => null,
            'push_public_key' => null,
            'push_auth_token' => null,
            'push_locale' => null,
        ])->save();

        $this->audit->record(
            userId: null,
            tenantId: $tenantId,
            action: 'worker.push_expired',
            modelType: WorkerDevice::class,
            modelId: (int) $device->id,
            payload: [
                'worker_id' => $workerId,
                'device_id' => (int) $device->id,
            ],
        );
    }
}
