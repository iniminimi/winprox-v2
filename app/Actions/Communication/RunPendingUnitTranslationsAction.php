<?php

namespace App\Actions\Communication;

use App\Enums\UnitTranslationStatus;
use App\Models\UnitTranslation;

class RunPendingUnitTranslationsAction
{
    public function __construct(private TranslateUnitAction $translateUnit) {}

    public function handle(?int $limit = null, ?int $actorUserId = null): int
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

        foreach ($rows as $row) {
            if ($row->unit === null) {
                continue;
            }

            $this->translateUnit->handle($row->unit, $row->locale, $actorUserId);
            $processed++;
        }

        return $processed;
    }
}
