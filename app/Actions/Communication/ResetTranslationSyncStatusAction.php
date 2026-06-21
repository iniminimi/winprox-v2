<?php

namespace App\Actions\Communication;

use App\Support\Translation\TranslationSyncStatusStore;

class ResetTranslationSyncStatusAction
{
    public function __construct(private TranslationSyncStatusStore $statusStore) {}

    public function handle(): void
    {
        $this->statusStore->clear();
    }
}
