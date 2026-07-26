<?php

namespace App\Actions\Communication;

use App\Enums\DocumentTranslationStatus;
use App\Events\Documents\DocumentTranslationImported;
use App\Models\Document;
use App\Models\DocumentTranslation;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;

class ImportDocumentTranslationsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureDocumentTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $documentId = (int) ($item['document_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $description = trim((string) ($item['description'] ?? $item['text'] ?? ''));

            if ($documentId <= 0 || $locale === '' || $description === '') {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if (mb_strlen($description) > TextDescriptionLimits::TRANSLATION_MAX) {
                throw ValidationException::withMessages([
                    "items.{$index}.description" => [__('issues.errors.translation_import_too_long')],
                ]);
            }

            $document = Document::query()->find($documentId);

            if ($document === null || ! $document->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.document_id" => [__('locations.documents.errors.translation_import_missing')],
                ]);
            }

            if ($locale === $document->normalizedOriginalLanguage()) {
                continue;
            }

            $this->ensureSlots->handle($document);

            $row = DocumentTranslation::query()
                ->where('document_id', $document->id)
                ->where('locale', $locale)
                ->firstOrFail();

            if (
                $row->status === DocumentTranslationStatus::Completed
                && $row->description === $description
            ) {
                continue;
            }

            $row->fill([
                'description' => $description,
                'status' => DocumentTranslationStatus::Completed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $document->tenant_id,
                'document.translation_imported',
                DocumentTranslation::class,
                (int) $row->id,
                [
                    'document_id' => $document->id,
                    'locale' => $locale,
                ],
            );

            DocumentTranslationImported::dispatch($row, $actorUserId);

            $imported++;
        }

        return $imported;
    }
}
