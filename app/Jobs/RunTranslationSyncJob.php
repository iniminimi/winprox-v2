<?php

namespace App\Jobs;

use App\Actions\Communication\RunTranslationSyncPipelineAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunTranslationSyncJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public function __construct(public int $actorUserId)
    {
        $this->timeout = (int) config('translation_sync.timeout_seconds', 7200);
    }

    public function handle(RunTranslationSyncPipelineAction $run): void
    {
        $run->handle($this->actorUserId);
    }
}
