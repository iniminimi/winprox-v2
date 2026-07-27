<?php

namespace App\Actions\Communication;

use App\Enums\CategoryTranslationStatus;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Validation\ValidationException;

class ImportCategoryTranslationsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureCategoryTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $categoryId = (int) ($item['category_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));

            if ($categoryId <= 0 || $locale === '' || $name === '') {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if (mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    "items.{$index}.name" => [__('locations.errors.translation_import_name_too_long')],
                ]);
            }

            $category = Category::query()->find($categoryId);

            if ($category === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.category_id" => [__('locations.errors.translation_import_missing')],
                ]);
            }

            if ($locale === $category->normalizedOriginalLanguage()) {
                continue;
            }

            $this->ensureSlots->handle($category);

            $row = CategoryTranslation::query()
                ->where('category_id', $category->id)
                ->where('locale', $locale)
                ->firstOrFail();

            if (
                $row->status === CategoryTranslationStatus::Completed
                && $row->name === $name
            ) {
                continue;
            }

            $row->fill([
                'name' => $name,
                'status' => CategoryTranslationStatus::Completed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $category->tenant_id,
                'category.translation_imported',
                CategoryTranslation::class,
                (int) $row->id,
                [
                    'category_id' => $category->id,
                    'locale' => $locale,
                ],
            );

            $imported++;
        }

        return $imported;
    }
}
