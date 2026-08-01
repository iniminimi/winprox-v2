<?php

namespace App\Actions\Communication;

use App\Enums\UnitCheckListTranslationStatus;
use App\Models\UnitCheckList;
use App\Models\UnitCheckListTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Validation\ValidationException;

class TranslateUnitCheckListAction
{
    public function __construct(
        private TranslationProviderInterface $translator,
        private AuditRecorder $audit,
        private EnsureUnitCheckListTranslationSlotsAction $ensureSlots,
    ) {}

    public function handle(UnitCheckList $list, string $targetLocale, ?int $actorUserId = null): UnitCheckListTranslation
    {
        if (! $list->is_active) {
            throw ValidationException::withMessages([
                'unit_check_list' => [__('unit_checks.lists.errors.translation_requires_active')],
            ]);
        }

        $targetLocale = LocaleSupport::normalize($targetLocale);

        if ($targetLocale === $list->normalizedOriginalLanguage()) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($list);

        $row = UnitCheckListTranslation::query()
            ->where('unit_check_list_id', $list->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if ($row->status === UnitCheckListTranslationStatus::Completed && $this->rowIsComplete($list, $row)) {
            return $row;
        }

        $sourceName = trim((string) $list->name);
        $sourceItems = $list->sourceItemLabels();

        $translatedName = '';
        if ($sourceName !== '') {
            $translatedName = $this->normalizeShortTranslation(
                trim($this->translator->translate($sourceName, $targetLocale)),
                $sourceName,
            );
        }

        $translatedItems = [];
        $itemsFailed = false;
        foreach ($sourceItems as $label) {
            $sourceLabel = (string) $label;
            $translatedLabel = $this->normalizeShortTranslation(
                trim($this->translator->translate($sourceLabel, $targetLocale)),
                $sourceLabel,
            );

            if (mb_strlen($translatedLabel) > 255) {
                $itemsFailed = true;
                break;
            }

            $translatedItems[] = $translatedLabel;
        }

        $failed = ($sourceName !== '' && ($translatedName === '' || mb_strlen($translatedName) > 255))
            || $itemsFailed;

        if ($failed) {
            $row->fill([
                'name' => null,
                'items' => null,
                'status' => UnitCheckListTranslationStatus::Failed,
            ])->save();

            $this->recordStored($row, $list, $targetLocale, $actorUserId, UnitCheckListTranslationStatus::Failed);

            return $row->fresh();
        }

        $row->fill([
            'name' => $sourceName !== '' ? $translatedName : null,
            'items' => $sourceItems !== [] ? $translatedItems : null,
            'status' => UnitCheckListTranslationStatus::Completed,
        ])->save();

        $this->recordStored($row, $list, $targetLocale, $actorUserId, UnitCheckListTranslationStatus::Completed);

        return $row->fresh();
    }

    private function recordStored(
        UnitCheckListTranslation $row,
        UnitCheckList $list,
        string $locale,
        ?int $actorUserId,
        UnitCheckListTranslationStatus $status,
    ): void {
        $this->audit->record(
            $actorUserId,
            (int) $list->tenant_id,
            'unit_check_list.translation_stored',
            UnitCheckListTranslation::class,
            (int) $row->id,
            [
                'unit_check_list_id' => $list->id,
                'locale' => $locale,
                'status' => $status->value,
            ],
        );
    }

    private function rowIsComplete(UnitCheckList $list, UnitCheckListTranslation $row): bool
    {
        $sourceName = trim((string) $list->name);
        $sourceItems = $list->sourceItemLabels();

        if ($sourceName !== '' && ! filled($row->name)) {
            return false;
        }

        if ($sourceItems !== []) {
            if (! is_array($row->items) || count($row->items) !== count($sourceItems)) {
                return false;
            }

            foreach ($row->items as $label) {
                if (! filled($label)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function normalizeShortTranslation(string $translated, string $source): string
    {
        if ($translated === '') {
            $translated = $source;
        }

        $translated = preg_replace('/\s+/u', ' ', $translated) ?? $translated;
        $translated = trim($translated);

        if (
            mb_strlen($translated) > 255
            || mb_strlen($translated) > max(48, mb_strlen($source) * 4)
        ) {
            return $source;
        }

        return $translated;
    }
}
