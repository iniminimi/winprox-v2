<?php

namespace App\Actions\Communication;

use App\Enums\EsgIndicatorTranslationStatus;
use App\Models\EsgIndicator;
use App\Models\EsgIndicatorTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Validation\ValidationException;

class TranslateEsgIndicatorAction
{
    public function __construct(
        private TranslationProviderInterface $translator,
        private AuditRecorder $audit,
        private EnsureEsgIndicatorTranslationSlotsAction $ensureSlots,
    ) {}

    public function handle(EsgIndicator $indicator, string $targetLocale, ?int $actorUserId = null): EsgIndicatorTranslation
    {
        if (! $indicator->is_active) {
            throw ValidationException::withMessages([
                'esg_indicator' => [__('esg.errors.translation_requires_active')],
            ]);
        }

        $targetLocale = LocaleSupport::normalize($targetLocale);

        if ($targetLocale === $indicator->normalizedOriginalLanguage()) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($indicator);

        $row = EsgIndicatorTranslation::query()
            ->where('esg_indicator_id', $indicator->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if ($row->status === EsgIndicatorTranslationStatus::Completed && $this->rowIsComplete($indicator, $row)) {
            return $row;
        }

        $stored = [];
        $failed = false;

        $sourceName = trim((string) $indicator->name);
        if ($sourceName !== '') {
            $translatedName = trim($this->translator->translate($sourceName, $targetLocale));
            if ($translatedName === '' || $translatedName === $sourceName || mb_strlen($translatedName) > 255) {
                $failed = true;
            } else {
                $stored['name'] = $translatedName;
            }
        }

        $sourceOptions = $indicator->normalizedChoiceOptions();
        if ($sourceOptions !== []) {
            $translatedOptions = [];
            foreach ($sourceOptions as $option) {
                $translatedOption = trim($this->translator->translate($option, $targetLocale));
                if ($translatedOption === '' || $translatedOption === $option || mb_strlen($translatedOption) > 255) {
                    $failed = true;
                    break;
                }

                $translatedOptions[] = $translatedOption;
            }

            if (! $failed) {
                $stored['options'] = $translatedOptions;
            }
        }

        if ($failed) {
            $row->fill([
                'name' => null,
                'options' => null,
                'status' => EsgIndicatorTranslationStatus::Failed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $indicator->tenant_id,
                'esg_indicator.translation_stored',
                EsgIndicatorTranslation::class,
                (int) $row->id,
                [
                    'esg_indicator_id' => $indicator->id,
                    'locale' => $targetLocale,
                    'status' => EsgIndicatorTranslationStatus::Failed->value,
                ],
            );

            return $row->fresh();
        }

        $row->fill([
            'name' => $stored['name'] ?? null,
            'options' => $stored['options'] ?? null,
            'status' => EsgIndicatorTranslationStatus::Completed,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $indicator->tenant_id,
            'esg_indicator.translation_stored',
            EsgIndicatorTranslation::class,
            (int) $row->id,
            [
                'esg_indicator_id' => $indicator->id,
                'locale' => $targetLocale,
                'status' => EsgIndicatorTranslationStatus::Completed->value,
            ],
        );

        return $row->fresh();
    }

    private function rowIsComplete(EsgIndicator $indicator, EsgIndicatorTranslation $row): bool
    {
        $sourceName = trim((string) $indicator->name);
        $sourceOptions = $indicator->normalizedChoiceOptions();

        if ($sourceName !== '' && ! filled($row->name)) {
            return false;
        }

        if ($sourceOptions !== []) {
            if (! is_array($row->options) || count($row->options) !== count($sourceOptions)) {
                return false;
            }

            foreach ($row->options as $option) {
                if (! filled($option)) {
                    return false;
                }
            }
        }

        return true;
    }
}
