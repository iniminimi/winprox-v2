<?php

namespace App\Actions\Communication;

use App\Enums\UnitTranslationStatus;
use App\Models\Unit;
use App\Models\UnitTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Translation\TranslationOutputGuard;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;

class TranslateUnitAction
{
    public function __construct(
        private TranslationProviderInterface $translator,
        private AuditRecorder $audit,
        private EnsureUnitTranslationSlotsAction $ensureSlots,
    ) {}

    public function handle(Unit $unit, string $targetLocale, ?int $actorUserId = null): UnitTranslation
    {
        if (! $unit->is_active) {
            throw ValidationException::withMessages([
                'unit' => [__('locations.units.errors.translation_requires_active')],
            ]);
        }

        $targetLocale = LocaleSupport::normalize($targetLocale);

        if ($targetLocale === $unit->normalizedOriginalLanguage()) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($unit);

        $row = UnitTranslation::query()
            ->where('unit_id', $unit->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if ($row->status === UnitTranslationStatus::Completed && $this->rowIsComplete($unit, $row)) {
            $unitUpdatedAt = $unit->updated_at;
            $rowUpdatedAt = $row->updated_at;

            if ($unitUpdatedAt !== null && $rowUpdatedAt !== null && $unitUpdatedAt->greaterThan($rowUpdatedAt)) {
                // Brontekst gewijzigd na laatste vertaling — opnieuw vertalen.
            } else {
                return $row;
            }
        }

        $stored = [];
        $failed = false;

        $sourceName = trim((string) $unit->name);
        if ($sourceName !== '') {
            $translatedName = trim($this->translator->translate($sourceName, $targetLocale));
            if (
                $translatedName === ''
                || $translatedName === $sourceName
                || TranslationOutputGuard::isUnusable($translatedName, $sourceName)
                || mb_strlen($translatedName) > 255
            ) {
                $failed = true;
            } else {
                $stored['name'] = $translatedName;
            }
        }

        $sourceDescription = trim((string) ($unit->description ?? ''));
        if ($sourceDescription !== '') {
            $translatedDescription = trim($this->translator->translate($sourceDescription, $targetLocale));
            if (
                $translatedDescription === ''
                || $translatedDescription === $sourceDescription
                || TranslationOutputGuard::isUnusable($translatedDescription, $sourceDescription)
                || mb_strlen($translatedDescription) > TextDescriptionLimits::TRANSLATION_MAX
            ) {
                $failed = true;
            } else {
                $stored['description'] = $translatedDescription;
            }
        }

        if ($failed) {
            $row->fill([
                'name' => null,
                'description' => null,
                'status' => UnitTranslationStatus::Failed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $unit->tenant_id,
                'unit.translation_stored',
                UnitTranslation::class,
                (int) $row->id,
                [
                    'unit_id' => $unit->id,
                    'locale' => $targetLocale,
                    'status' => UnitTranslationStatus::Failed->value,
                ],
            );

            return $row->fresh();
        }

        $row->fill([
            'name' => $stored['name'] ?? null,
            'description' => $stored['description'] ?? null,
            'status' => UnitTranslationStatus::Completed,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $unit->tenant_id,
            'unit.translation_stored',
            UnitTranslation::class,
            (int) $row->id,
            [
                'unit_id' => $unit->id,
                'locale' => $targetLocale,
                'status' => UnitTranslationStatus::Completed->value,
            ],
        );

        return $row->fresh();
    }

    private function rowIsComplete(Unit $unit, UnitTranslation $row): bool
    {
        $sourceName = trim((string) $unit->name);
        $sourceDescription = trim((string) ($unit->description ?? ''));

        if ($sourceName !== '' && ! filled($row->name)) {
            return false;
        }

        if ($sourceDescription !== '' && ! filled($row->description)) {
            return false;
        }

        return true;
    }
}
