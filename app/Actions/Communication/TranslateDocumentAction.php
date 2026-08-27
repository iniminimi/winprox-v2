<?php

namespace App\Actions\Communication;

use App\Enums\DocumentTranslationStatus;
use App\Models\Document;
use App\Models\DocumentTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Translation\TranslationOutputGuard;
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
        $sourceLocale = $document->normalizedOriginalLanguage();

        if ($targetLocale === $sourceLocale) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($document);

        $row = DocumentTranslation::query()
            ->where('document_id', $document->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if (
            $row->status === DocumentTranslationStatus::Completed
            && filled($row->description)
            && ! TranslationOutputGuard::isUntranslatedEcho(
                (string) $row->description,
                (string) $document->description,
                $targetLocale,
                $sourceLocale,
            )
        ) {
            return $row;
        }

        $sourceText = trim((string) $document->description);
        $translated = trim($this->translator->translate($sourceText, $targetLocale, $sourceLocale));

        if (
            $translated === ''
            || TranslationOutputGuard::isUnusable($translated, $sourceText)
            || TranslationOutputGuard::isUntranslatedEcho($translated, $sourceText, $targetLocale, $sourceLocale)
        ) {
            return $this->storeFailed($row, $document, $targetLocale, $actorUserId, 'translation_empty_or_unusable');
        }

        if (mb_strlen($translated) > TextDescriptionLimits::TRANSLATION_MAX) {
            return $this->storeFailed($row, $document, $targetLocale, $actorUserId, 'translation_too_long');
        }

        $row->fill([
            'description' => $translated,
            'status' => DocumentTranslationStatus::Completed,
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
                'status' => DocumentTranslationStatus::Completed->value,
            ],
        );

        return $row->fresh();
    }

    private function storeFailed(
        DocumentTranslation $row,
        Document $document,
        string $targetLocale,
        ?int $actorUserId,
        string $reason,
    ): DocumentTranslation {
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
                'reason' => $reason,
            ],
        );

        return $row->fresh();
    }
}
