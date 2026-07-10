<?php

namespace App\Actions\Communication;

use App\Enums\EsgIndicatorTranslationStatus;
use App\Models\EsgIndicatorTranslation;

class ExportPendingEsgIndicatorTranslationsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        return EsgIndicatorTranslation::query()
            ->where('status', EsgIndicatorTranslationStatus::Pending)
            ->whereHas('indicator', fn ($query) => $query->where('is_active', true))
            ->with('indicator')
            ->orderBy('esg_indicator_id')
            ->orderBy('locale')
            ->get()
            ->map(function (EsgIndicatorTranslation $row): array {
                $indicator = $row->indicator;

                return [
                    'esg_indicator_id' => $indicator->id,
                    'tenant_id' => $indicator->tenant_id,
                    'source_locale' => $indicator->normalizedOriginalLanguage(),
                    'source_name' => (string) $indicator->name,
                    'source_options' => $indicator->normalizedChoiceOptions(),
                    'locale' => $row->locale,
                    'status' => EsgIndicatorTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();
    }
}
