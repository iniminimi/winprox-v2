<?php

namespace App\Actions\Communication;

use App\Enums\CategoryTranslationStatus;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureCategoryTranslationSlotsAction
{
    public function handle(Category $category): void
    {
        if (trim((string) $category->name) === '') {
            return;
        }

        foreach (LocaleSupport::targetLocalesForSource($category->original_language) as $locale) {
            $row = CategoryTranslation::firstOrCreate(
                [
                    'category_id' => $category->id,
                    'locale' => $locale,
                ],
                [
                    'status' => CategoryTranslationStatus::Pending,
                ],
            );

            // Self-heal old failed rows (empty value) so export can retry.
            if (
                $row->status === CategoryTranslationStatus::Failed
                && blank($row->name)
            ) {
                $row->fill([
                    'status' => CategoryTranslationStatus::Pending,
                ])->save();
            }
        }
    }
}
