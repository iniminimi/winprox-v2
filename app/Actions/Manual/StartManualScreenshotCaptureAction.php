<?php

namespace App\Actions\Manual;

use App\Enums\ManualCaptureRunStatus;
use App\Jobs\CaptureManualScreenshotsJob;
use App\Support\Manual\ManualCaptureStatusStore;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

class StartManualScreenshotCaptureAction
{
    public function __construct(private ManualCaptureStatusStore $statusStore) {}

    public function handle(int $actorUserId): ManualCaptureRunStatus
    {
        $this->assertConfigured();

        $lock = Cache::lock('manual-screenshot-capture', (int) config('manual_capture.timeout_seconds', 600));

        if (! $lock->get()) {
            throw new RuntimeException('manual_capture_already_running');
        }

        try {
            $current = $this->statusStore->read();
            $active = $current['status'] ?? null;
            if (in_array($active, [
                ManualCaptureRunStatus::Running->value,
                ManualCaptureRunStatus::Queued->value,
            ], true)) {
                throw new RuntimeException('manual_capture_already_running');
            }

            $this->statusStore->write(ManualCaptureRunStatus::Queued, $actorUserId, [
                'started_at' => now()->toIso8601String(),
                'message' => null,
            ]);

            CaptureManualScreenshotsJob::dispatch($actorUserId);
        } finally {
            $lock->release();
        }

        return ManualCaptureRunStatus::Queued;
    }

    private function assertConfigured(): void
    {
        $email = (string) config('manual_capture.email');
        $password = (string) config('manual_capture.password');

        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('manual_capture_not_configured');
        }
    }
}
