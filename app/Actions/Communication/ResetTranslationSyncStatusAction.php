<?php

namespace App\Actions\Communication;

use App\Support\Translation\TranslationSyncCancellation;
use App\Support\Translation\TranslationSyncStatusStore;

class ResetTranslationSyncStatusAction
{
    public function __construct(private TranslationSyncStatusStore $statusStore) {}

    public function handle(): void
    {
        TranslationSyncCancellation::clear();
        $this->statusStore->clear();
    }
}
