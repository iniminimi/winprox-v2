<?php

namespace App\Actions\Communication;

use App\Enums\DocumentTranslationStatus;
use App\Models\Document;
use App\Models\DocumentTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;

class TranslateDocumentAction
{
    public function __construct(
        private TranslationProviderInterface $translator,
        private AuditRecorder $audit,
        private EnsureDocumentTranslationSlotsAction $ensureSlots,
    ) {}

    public function handle(Document $document, string $targetLocale, ?int $actorUserId = null): DocumentTranslation
    {
        if (! $document->is_active) {
            throw ValidationException::withMessages([
                'document' => [__('locations.documents.errors.translation_requires_active')],
            ]);
        }

        $targetLocale = LocaleSupport::normalize($targetLocale);

        if ($targetLocale === $document->normalizedOriginalLanguage()) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($document);

        $row = DocumentTranslation::query()
            ->where('document_id', $document->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if ($row->status === DocumentTranslationStatus::Completed && filled($row->description)) {
            return $row;
        }

        $sourceText = trim((string) $document->description);
        $translated = trim($this->translator->translate($sourceText, $targetLocale));
        $stored = $translated !== '' ? $translated : $sourceText;

        if (mb_strlen($stored) > TextDescriptionLimits::TRANSLATION_MAX) {
            $row->fill([
                'description' => null,
                'status' => DocumentTranslationStatus::Failed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $document->tenant_id,
                'document.translation_stored',
                DocumentTranslation::class,
                (int) $row->id,
                [
                    'document_id' => $document->id,
                    'locale' => $targetLocale,
                    'status' => DocumentTranslationStatus::Failed->value,
                    'reason' => 'translation_too_long',
                ],
            );

            return $row->fresh();
        }

        $status = ($translated !== '' && $translated !== $sourceText)
            ? DocumentTranslationStatus::Completed
            : DocumentTranslationStatus::Failed;

        $row->fill([
            'description' => $stored,
            'status' => $status,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $document->tenant_id,
            'document.translation_stored',
            DocumentTranslation::class,
            (int) $row->id,
            [
                'document_id' => $document->id,
                'locale' => $targetLocale,
                'status' => $status->value,
            ],
        );

        return $row->fresh();
    }
}
