<?php

declare(strict_types=1);

namespace App\Actions\Checkmate;

use App\Actions\Locations\CreateCategoryAction;
use App\Enums\CategoryTranslationStatus;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Tenant;
use App\Support\Checkmate\CheckmateDefaultCategoryCatalog;
use App\Support\Translation\LocaleSupport;

/**
 * Zorgt dat een Checkmate-tenant minstens één categorie heeft (stil),
 * zodat Locaties/Units-gates niet om «categorie aanmaken» vragen.
 */
class EnsureCheckmateDefaultCategoryAction
{
    public function __construct(
        private CreateCategoryAction $createCategory,
    ) {}

    public function handle(Tenant $tenant, ?int $actorUserId = null): ?Category
    {
        if (! $tenant->checkmateMode()) {
            return null;
        }

        $existing = Category::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->first();

        if ($existing instanceof Category) {
            return $existing;
        }

        $category = $this->createCategory->handle(
            (int) $tenant->id,
            [
                'name' => CheckmateDefaultCategoryCatalog::SOURCE_NAME,
                'original_language' => CheckmateDefaultCategoryCatalog::SOURCE_LOCALE,
                'allow_gps_location' => true,
                'is_reservable' => false,
                'allow_unit_checks' => false,
                'allow_unit_measurements' => false,
                'require_reporter_contact' => false,
                'require_reporter_email_verification' => false,
                'show_previous_issues' => false,
            ],
            $actorUserId,
        );

        $this->fillTranslations($category);

        return $category;
    }

    private function fillTranslations(Category $category): void
    {
        $sourceLocale = LocaleSupport::normalize($category->original_language)
            ?? CheckmateDefaultCategoryCatalog::SOURCE_LOCALE;

        foreach (CheckmateDefaultCategoryCatalog::namesByLocale() as $locale => $name) {
            if ($locale === $sourceLocale) {
                continue;
            }

            CategoryTranslation::query()->updateOrCreate(
                [
                    'category_id' => $category->id,
                    'locale' => $locale,
                ],
                [
                    'name' => $name,
                    'status' => CategoryTranslationStatus::Completed,
                ],
            );
        }
    }
}
