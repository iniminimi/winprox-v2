<?php

namespace App\Actions\Communication;

use App\Enums\UnitCheckListTranslationStatus;
use App\Models\UnitCheckListTranslation;

class ExportPendingUnitCheckListTranslationsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        return UnitCheckListTranslation::query()
            ->where('status', UnitCheckListTranslationStatus::Pending)
            ->whereHas('list', fn ($query) => $query->where('is_active', true))
            ->with('list.items')
            ->orderBy('unit_check_list_id')
            ->orderBy('locale')
            ->get()
            ->map(function (UnitCheckListTranslation $row): array {
                $list = $row->list;

                return [
                    'unit_check_list_id' => $list->id,
                    'tenant_id' => $list->tenant_id,
                    'source_locale' => $list->normalizedOriginalLanguage(),
                    'source_name' => (string) $list->name,
                    'source_items' => $list->sourceItemLabels(),
                    'locale' => $row->locale,
                    'status' => UnitCheckListTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();
    }
}
