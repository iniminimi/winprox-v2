<?php

namespace App\Actions\Communication;

use App\Enums\CategoryTranslationStatus;
use App\Models\CategoryTranslation;

class RunPendingCategoryTranslationsAction
{
    public function __construct(private TranslateCategoryAction $translateCategory) {}

    public function handle(?int $limit = null, ?int $actorUserId = null, ?callable $onProgress = null): int
    {
        $limit = $limit ?? (int) config('ollama.batch_limit', 25);

        $rows = CategoryTranslation::query()
            ->where('status', CategoryTranslationStatus::Pending)
            ->whereHas('category', fn ($query) => $query->where('name', '!=', ''))
            ->with('category')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $total = $rows->count();
        $onProgress?->__invoke($processed, $total, null, null);

        foreach ($rows as $row) {
            if ($row->category === null) {
                continue;
            }

            $this->translateCategory->handle($row->category, $row->locale, $actorUserId);
            $processed++;
            $onProgress?->__invoke($processed, $total, $row->id, $row->locale);
        }

        return $processed;
    }
}
