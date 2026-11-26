<?php

namespace App\Actions\Communication;

use App\Models\Tenant;
use App\Enums\DocumentTranslationStatus;
use App\Models\Document;
use App\Models\DocumentTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureDocumentTranslationSlotsAction
{
    public function handle(Document $document): void
    {
        if (! Tenant::translationsEnabledFor((int) $document->tenant_id)) {
            return;
        }

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
