<?php

namespace App\Actions\Communication;

use App\Enums\UnitTranslationStatus;
use App\Models\UnitTranslation;

class ExportPendingUnitTranslationsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        return UnitTranslation::query()
            ->where('status', UnitTranslationStatus::Pending)
            ->whereHas('unit', fn ($query) => $query->where('is_active', true))
            ->with('unit')
            ->orderBy('unit_id')
            ->orderBy('locale')
            ->get()
            ->map(function (UnitTranslation $row): array {
                $unit = $row->unit;

                return [
                    'unit_id' => $unit->id,
                    'tenant_id' => $unit->tenant_id,
                    'source_locale' => $unit->normalizedOriginalLanguage(),
                    'source_name' => (string) $unit->name,
                    'source_description' => (string) ($unit->description ?? ''),
                    'locale' => $row->locale,
                    'status' => UnitTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();
    }
}
