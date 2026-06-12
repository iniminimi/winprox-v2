<?php

namespace App\Actions\Manual;

use App\Support\Manual\ManualCaptureStatusStore;

class ReadManualScreenshotCaptureStatusAction
{
    public function __construct(private ManualCaptureStatusStore $statusStore) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(): ?array
    {
        return $this->statusStore->read();
    }
}
