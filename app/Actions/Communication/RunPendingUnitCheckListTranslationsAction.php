<?php

namespace App\Actions\Communication;

use App\Enums\UnitCheckListTranslationStatus;
use App\Models\UnitCheckListTranslation;

class RunPendingUnitCheckListTranslationsAction
{
    public function __construct(private TranslateUnitCheckListAction $translateList) {}

    public function handle(?int $limit = null, ?int $actorUserId = null, ?callable $onProgress = null): int
    {
        $limit = $limit ?? (int) config('ollama.batch_limit', 25);

        $rows = UnitCheckListTranslation::query()
            ->where('status', UnitCheckListTranslationStatus::Pending)
            ->whereHas('list', fn ($query) => $query->where('is_active', true))
            ->with('list.items')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        $total = $rows->count();
        $onProgress?->__invoke($processed, $total, null, null);

        foreach ($rows as $row) {
            if ($row->list === null) {
                continue;
            }

            $this->translateList->handle($row->list, $row->locale, $actorUserId);
            $processed++;
            $onProgress?->__invoke($processed, $total, $row->id, $row->locale);
        }

        return $processed;
    }
}
