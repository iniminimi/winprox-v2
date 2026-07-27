<?php

namespace App\Actions\Communication;

use App\Models\Category;
use App\Models\CategoryTranslation;

class BackfillCategoryTranslationSlotsAction
{
    public function __construct(private EnsureCategoryTranslationSlotsAction $ensureSlots) {}

    /**
     * @return array{categories: int, slots_created: int}
     */
    public function handle(?int $tenantId = null): array
    {
        $processed = 0;
        $slotsCreated = 0;

        Category::query()
            ->where('name', '!=', '')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($categories) use (&$processed, &$slotsCreated): void {
                foreach ($categories as $category) {
                    $before = CategoryTranslation::query()
                        ->where('category_id', $category->id)
                        ->count();

                    $this->ensureSlots->handle($category);

                    $after = CategoryTranslation::query()
                        ->where('category_id', $category->id)
                        ->count();

                    $processed++;
                    $slotsCreated += max(0, $after - $before);
                }
            });

        return [
            'categories' => $processed,
            'slots_created' => $slotsCreated,
        ];
    }
}
