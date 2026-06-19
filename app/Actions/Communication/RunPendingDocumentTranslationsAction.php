<?php

namespace App\Actions\Communication;

use App\Enums\DocumentTranslationStatus;
use App\Models\DocumentTranslation;

class RunPendingDocumentTranslationsAction
{
    public function __construct(private TranslateDocumentAction $translateDocument) {}

    public function handle(?int $limit = null, ?int $actorUserId = null): int
    {
        $limit = $limit ?? (int) config('ollama.batch_limit', 25);

        $rows = DocumentTranslation::query()
            ->where('status', DocumentTranslationStatus::Pending)
            ->whereHas('document', fn ($query) => $query->where('is_active', true))
            ->with('document')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($rows as $row) {
            if ($row->document === null) {
                continue;
            }

            $this->translateDocument->handle($row->document, $row->locale, $actorUserId);
            $processed++;
        }

        return $processed;
    }
}
