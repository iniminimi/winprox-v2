<?php

namespace App\Actions\Communication;

use App\Enums\CategoryTranslationStatus;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Support\Audit\AuditRecorder;

class InvalidateCategoryTranslationsOnSourceChangeAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Category $category, string $previousName, ?int $actorUserId = null): void
    {
        if (trim($previousName) === trim((string) $category->name)) {
            return;
        }

        if (trim((string) $category->name) === '') {
            return;
        }

        $source = $category->normalizedOriginalLanguage();

        $invalidated = CategoryTranslation::query()
            ->where('category_id', $category->id)
            ->where('locale', '!=', $source)
            ->where(function ($query) {
                $query->where('status', '!=', CategoryTranslationStatus::Pending->value)
                    ->orWhereNotNull('name');
            })
            ->update([
                'name' => null,
                'status' => CategoryTranslationStatus::Pending->value,
            ]);

        if ($invalidated === 0) {
            return;
        }

        $this->audit->record(
            $actorUserId,
            (int) $category->tenant_id,
            'category.translations_invalidated',
            Category::class,
            (int) $category->id,
            [
                'category_id' => $category->id,
                'rows' => $invalidated,
                'source_locale' => $source,
            ],
        );
    }
}
