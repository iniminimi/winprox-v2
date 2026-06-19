<?php

namespace App\Actions\Communication;

use App\Enums\DocumentTranslationStatus;
use App\Models\Document;
use App\Models\DocumentTranslation;
use App\Support\Audit\AuditRecorder;

class InvalidateDocumentTranslationsOnSourceChangeAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Document $document, string $previousDescription, ?int $actorUserId = null): void
    {
        if (trim($previousDescription) === trim((string) $document->description)) {
            return;
        }

        if (! $document->is_active) {
            return;
        }

        $source = $document->normalizedOriginalLanguage();

        $invalidated = DocumentTranslation::query()
            ->where('document_id', $document->id)
            ->where('locale', '!=', $source)
            ->where(function ($query) {
                $query->where('status', '!=', DocumentTranslationStatus::Pending->value)
                    ->orWhereNotNull('description');
            })
            ->update([
                'description' => null,
                'status' => DocumentTranslationStatus::Pending->value,
            ]);

        if ($invalidated === 0) {
            return;
        }

        $this->audit->record(
            $actorUserId,
            (int) $document->tenant_id,
            'document.translations_invalidated',
            Document::class,
            (int) $document->id,
            [
                'document_id' => $document->id,
                'rows' => $invalidated,
                'source_locale' => $source,
            ],
        );
    }
}
