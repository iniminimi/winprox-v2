<?php

namespace App\Actions\Communication;

use App\Enums\TranslationSyncPhase;
use App\Support\Translation\TranslationSyncCancellation;
use App\Support\Translation\TranslationSyncStatusStore;
use RuntimeException;

class CancelTranslationSyncAction
{
    public function __construct(private TranslationSyncStatusStore $statusStore) {}

    public function handle(int $actorUserId): void
    {
        $current = $this->statusStore->read();
        $phase = TranslationSyncPhase::tryFrom((string) ($current['phase'] ?? ''));

        if ($phase === null || ! $phase->isActive() || $phase === TranslationSyncPhase::Cancelling) {
            throw new RuntimeException('translation_sync_nothing_to_cancel');
        }

        TranslationSyncCancellation::request();

        $this->statusStore->write(TranslationSyncPhase::Cancelling, $actorUserId, [
            'total' => (int) ($current['total'] ?? 0),
            'completed' => (int) ($current['completed'] ?? 0),
            'message' => 'cancel_requested',
        ]);
    }
}
