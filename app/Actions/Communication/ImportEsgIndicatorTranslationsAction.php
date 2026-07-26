<?php

namespace App\Actions\Communication;

use App\Enums\EsgIndicatorTranslationStatus;
use App\Models\EsgIndicator;
use App\Models\EsgIndicatorTranslation;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Validation\ValidationException;

class ImportEsgIndicatorTranslationsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureEsgIndicatorTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $indicatorId = (int) ($item['esg_indicator_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));
            $options = $item['options'] ?? null;

            if ($indicatorId <= 0 || $locale === '' || $name === '') {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if ($name !== '' && mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    "items.{$index}.name" => [__('esg.errors.translation_import_name_too_long')],
                ]);
            }

            if ($options !== null && ! is_array($options)) {
                throw ValidationException::withMessages([
                    "items.{$index}.options" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if (is_array($options)) {
                foreach ($options as $optionIndex => $option) {
                    if (! is_string($option) && ! is_numeric($option)) {
                        throw ValidationException::withMessages([
                            "items.{$index}.options.{$optionIndex}" => [__('issues.errors.translation_import_invalid')],
                        ]);
                    }

                    if (mb_strlen(trim((string) $option)) > 255) {
                        throw ValidationException::withMessages([
                            "items.{$index}.options.{$optionIndex}" => [__('esg.errors.translation_import_option_too_long')],
                        ]);
                    }
                }
            }

            $indicator = EsgIndicator::query()->find($indicatorId);

            if ($indicator === null || ! $indicator->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.esg_indicator_id" => [__('esg.errors.translation_import_missing')],
                ]);
            }

            if ($locale === $indicator->normalizedOriginalLanguage()) {
                continue;
            }

            $sourceOptions = $indicator->normalizedChoiceOptions();
            $normalizedOptions = null;

            if ($sourceOptions !== []) {
                if (! is_array($options) || count($options) !== count($sourceOptions)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.options" => [__('esg.errors.translation_import_options_mismatch')],
                    ]);
                }

                $normalizedOptions = array_map(
                    static fn (mixed $option): string => trim((string) $option),
                    $options,
                );
            }

            $this->ensureSlots->handle($indicator);

            $row = EsgIndicatorTranslation::query()
                ->where('esg_indicator_id', $indicator->id)
                ->where('locale', $locale)
                ->firstOrFail();

            if (
                $row->status === EsgIndicatorTranslationStatus::Completed
                && $row->name === $name
                && $row->options == $normalizedOptions
            ) {
                continue;
            }

            $row->fill([
                'name' => $name,
                'options' => $normalizedOptions,
                'status' => EsgIndicatorTranslationStatus::Completed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $indicator->tenant_id,
                'esg_indicator.translation_imported',
                EsgIndicatorTranslation::class,
                (int) $row->id,
                [
                    'esg_indicator_id' => $indicator->id,
                    'locale' => $locale,
                ],
            );

            $imported++;
        }

        return $imported;
    }
}
