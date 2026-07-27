<?php

namespace App\Actions\Communication;

use App\Enums\CategoryTranslationStatus;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Validation\ValidationException;

class TranslateCategoryAction
{
    public function __construct(
        private TranslationProviderInterface $translator,
        private AuditRecorder $audit,
        private EnsureCategoryTranslationSlotsAction $ensureSlots,
    ) {}

    public function handle(Category $category, string $targetLocale, ?int $actorUserId = null): CategoryTranslation
    {
        $targetLocale = LocaleSupport::normalize($targetLocale);

        if ($targetLocale === $category->normalizedOriginalLanguage()) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($category);

        $row = CategoryTranslation::query()
            ->where('category_id', $category->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if ($row->status === CategoryTranslationStatus::Completed && filled($row->name)) {
            return $row;
        }

        $sourceName = trim((string) $category->name);
        $translatedName = $sourceName !== ''
            ? trim($this->translator->translate($sourceName, $targetLocale))
            : '';

        $failed = $sourceName === ''
            || $translatedName === ''
            || $translatedName === $sourceName
            || mb_strlen($translatedName) > 255;

        if ($failed) {
            $row->fill([
                'name' => null,
                'status' => CategoryTranslationStatus::Failed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $category->tenant_id,
                'category.translation_stored',
                CategoryTranslation::class,
                (int) $row->id,
                [
                    'category_id' => $category->id,
                    'locale' => $targetLocale,
                    'status' => CategoryTranslationStatus::Failed->value,
                ],
            );

            return $row->fresh();
        }

        $row->fill([
            'name' => $translatedName,
            'status' => CategoryTranslationStatus::Completed,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $category->tenant_id,
            'category.translation_stored',
            CategoryTranslation::class,
            (int) $row->id,
            [
                'category_id' => $category->id,
                'locale' => $targetLocale,
                'status' => CategoryTranslationStatus::Completed->value,
            ],
        );

        return $row->fresh();
    }
}
