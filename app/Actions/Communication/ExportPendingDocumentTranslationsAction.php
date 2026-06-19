<?php

namespace App\Actions\Communication;

use App\Enums\DocumentTranslationStatus;
use App\Models\DocumentTranslation;

class ExportPendingDocumentTranslationsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        return DocumentTranslation::query()
            ->where('status', DocumentTranslationStatus::Pending)
            ->whereHas('document', fn ($query) => $query->where('is_active', true))
            ->with('document')
            ->orderBy('document_id')
            ->orderBy('locale')
            ->get()
            ->map(function (DocumentTranslation $row): array {
                $document = $row->document;

                return [
                    'document_id' => $document->id,
                    'tenant_id' => $document->tenant_id,
                    'source_locale' => $document->normalizedOriginalLanguage(),
                    'source_text' => (string) $document->description,
                    'locale' => $row->locale,
                    'status' => DocumentTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();
    }
}
