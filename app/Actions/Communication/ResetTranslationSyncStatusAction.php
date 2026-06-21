<?php

namespace App\Actions\Communication;

use App\Enums\TranslationSyncPhase;
use App\Support\Translation\TranslationSyncCancellation;
use App\Support\Translation\TranslationSyncStatusStore;

class ResetTranslationSyncStatusAction
{
    public function __construct(private TranslationSyncStatusStore $statusStore) {}

    public function handle(): void
    {
        $current = $this->statusStore->read();
        $phase = TranslationSyncPhase::tryFrom((string) ($current['phase'] ?? ''));
        $keepCancelFlag = ($phase?->isActive() ?? false) || TranslationSyncCancellation::requested();

        $this->statusStore->clear();

        if (! $keepCancelFlag) {
            TranslationSyncCancellation::clear();
        }
    }
}
