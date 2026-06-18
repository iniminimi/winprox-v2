<?php

namespace App\Actions\Communication;

use App\Support\Translation\TranslationSyncStatusStore;

class ReadTranslationSyncStatusAction
{
    public function __construct(private TranslationSyncStatusStore $statusStore) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(): ?array
    {
        return $this->statusStore->read();
    }
}
