<?php

namespace App\Actions\Communication;

use App\Enums\TranslationSyncPhase;
use App\Support\Translation\TranslationSyncCancellation;
use App\Support\Translation\TranslationSyncStatusStore;

class ReadTranslationSyncStatusAction
{
    public function __construct(private TranslationSyncStatusStore $statusStore) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(): ?array
    {
        $status = $this->statusStore->read();

        if ($status === null) {
            return null;
        }

        if ($this->statusStore->isCancellingTimedOut($status)) {
            $this->finalizeCancellation($status);

            return $this->statusStore->read();
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function finalizeCancellation(array $status): void
    {
        $this->statusStore->write(TranslationSyncPhase::Cancelled, isset($status['actor_user_id']) ? (int) $status['actor_user_id'] : null, [
            'finished_at' => now()->toIso8601String(),
            'message' => 'cancelled',
            'total' => (int) ($status['total'] ?? 0),
            'completed' => (int) ($status['completed'] ?? 0),
        ]);
        TranslationSyncCancellation::clear();
    }
}
