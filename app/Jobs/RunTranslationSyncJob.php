<?php

namespace App\Jobs;

use App\Actions\Communication\RunTranslationSyncPipelineAction;
use App\Enums\TranslationSyncPhase;
use App\Support\Translation\TranslationSyncCancelledException;
use App\Support\Translation\TranslationSyncStatusStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

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

    public function failed(?Throwable $exception): void
    {
        if ($exception instanceof TranslationSyncCancelledException) {
            return;
        }

        $status = app(TranslationSyncStatusStore::class)->read();
        $phase = TranslationSyncPhase::tryFrom((string) ($status['phase'] ?? ''));

        if (in_array($phase, [TranslationSyncPhase::Cancelled, TranslationSyncPhase::Cancelling], true)) {
            return;
        }

        if ($phase?->isActive()) {
            app(TranslationSyncStatusStore::class)->write(TranslationSyncPhase::Failed, $this->actorUserId, [
                'finished_at' => now()->toIso8601String(),
                'message' => $exception?->getMessage() ?? 'translation_sync_job_failed',
            ]);
        }
    }
}
