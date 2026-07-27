<?php

namespace App\Actions\Communication;

use App\Enums\CategoryTranslationStatus;
use App\Models\CategoryTranslation;

class ExportPendingCategoryTranslationsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        return CategoryTranslation::query()
            ->where('status', CategoryTranslationStatus::Pending)
            ->whereHas('category', fn ($query) => $query->where('name', '!=', ''))
            ->with('category')
            ->orderBy('category_id')
            ->orderBy('locale')
            ->get()
            ->map(function (CategoryTranslation $row): array {
                $category = $row->category;

                return [
                    'category_id' => $category->id,
                    'tenant_id' => $category->tenant_id,
                    'source_locale' => $category->normalizedOriginalLanguage(),
                    'source_name' => (string) $category->name,
                    'locale' => $row->locale,
                    'status' => CategoryTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();
    }
}
