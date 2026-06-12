<?php

namespace App\Jobs;

use App\Actions\Manual\ExecuteManualScreenshotCaptureAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CaptureManualScreenshotsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public function __construct(public int $actorUserId)
    {
        $this->timeout = (int) config('manual_capture.timeout_seconds', 600);
    }

    public function handle(ExecuteManualScreenshotCaptureAction $execute): void
    {
        $execute->handle($this->actorUserId);
    }
}
