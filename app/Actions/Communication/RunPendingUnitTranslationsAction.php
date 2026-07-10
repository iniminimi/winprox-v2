<?php

namespace App\Actions\Communication;

use App\Enums\UnitTranslationStatus;
use App\Models\UnitTranslation;

class RunPendingUnitTranslationsAction
{
    public function __construct(private TranslateUnitAction $translateUnit) {}

    public function handle(?int $limit = null, ?int $actorUserId = null, ?callable $onProgress = null): int
    {
        $limit = $limit ?? (int) config('ollama.batch_limit', 25);

        $rows = UnitTranslation::query()
            ->where('status', UnitTranslationStatus::Pending)
            ->whereHas('unit', fn ($query) => $query->where('is_active', true))
            ->with('unit')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        $total = $rows->count();
        $onProgress?->__invoke($processed, $total, null, null);

        foreach ($rows as $row) {
            if ($row->unit === null) {
                continue;
            }

            $this->translateUnit->handle($row->unit, $row->locale, $actorUserId);
            $processed++;
            $onProgress?->__invoke($processed, $total, $row->id, $row->locale);
        }

        return $processed;
    }
}
