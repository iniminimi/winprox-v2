<?php

namespace App\Actions\Communication;

use App\Enums\DocumentTranslationStatus;
use App\Models\Document;
use App\Models\DocumentTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureDocumentTranslationSlotsAction
{
    public function handle(Document $document): void
    {
        if (! $document->is_active || ! filled(trim((string) ($document->description ?? '')))) {
            return;
        }

        foreach (LocaleSupport::targetLocalesForSource($document->original_language) as $locale) {
            DocumentTranslation::firstOrCreate(
                [
                    'document_id' => $document->id,
                    'locale' => $locale,
                ],
                [
                    'status' => DocumentTranslationStatus::Pending,
                ],
            );
        }
    }
}
