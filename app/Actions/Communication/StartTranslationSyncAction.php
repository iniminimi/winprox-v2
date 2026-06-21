<?php

namespace App\Actions\Communication;

use App\Contracts\TranslationSyncRemoteClient;
use App\Enums\TranslationSyncPhase;
use App\Jobs\RunTranslationSyncJob;
use App\Support\Translation\TranslationSyncRemoteGateway;
use App\Support\Translation\TranslationSyncStatusStore;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

class StartTranslationSyncAction
{
    public function __construct(
        private TranslationSyncStatusStore $statusStore,
        private TranslationSyncRemoteClient $remote,
    ) {}

    public function handle(int $actorUserId): TranslationSyncPhase
    {
        $this->remote->assertConfigured();

        $lock = Cache::lock('translation-sync-run', (int) config('translation_sync.timeout_seconds', 7200));

        if (! $lock->get()) {
            throw new RuntimeException('translation_sync_already_running');
        }

        try {
            $current = $this->statusStore->read();
            $phase = TranslationSyncPhase::tryFrom((string) ($current['phase'] ?? ''));
            if ($phase?->isActive()) {
                if ($this->statusStore->isStale($current)) {
                    $this->statusStore->clear();
                } else {
                    throw new RuntimeException('translation_sync_already_running');
                }
            }

            $this->statusStore->write(TranslationSyncPhase::Queued, $actorUserId, [
                'started_at' => now()->toIso8601String(),
                'message' => null,
                'total' => 0,
                'completed' => 0,
            ]);

            RunTranslationSyncJob::dispatch($actorUserId);
        } finally {
            $lock->release();
        }

        return TranslationSyncPhase::Queued;
    }
}
