<?php

namespace App\Actions\Communication;

use App\Enums\EsgIndicatorTranslationStatus;
use App\Models\EsgIndicatorTranslation;

class RunPendingEsgIndicatorTranslationsAction
{
    public function __construct(private TranslateEsgIndicatorAction $translateIndicator) {}

    public function handle(?int $limit = null, ?int $actorUserId = null, ?callable $onProgress = null): int
    {
        $limit = $limit ?? (int) config('ollama.batch_limit', 25);

        $rows = EsgIndicatorTranslation::query()
            ->where('status', EsgIndicatorTranslationStatus::Pending)
            ->whereHas('indicator', fn ($query) => $query->where('is_active', true))
            ->with('indicator')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        $total = $rows->count();
        $onProgress?->__invoke($processed, $total, null, null);

        foreach ($rows as $row) {
            if ($row->indicator === null) {
                continue;
            }

            $this->translateIndicator->handle($row->indicator, $row->locale, $actorUserId);
            $processed++;
            $onProgress?->__invoke($processed, $total, $row->id, $row->locale);
        }

        return $processed;
    }
}
