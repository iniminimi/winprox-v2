<?php

namespace App\Actions\Communication;

use App\Enums\UnitTranslationStatus;
use App\Events\Units\UnitTranslationImported;
use App\Models\Unit;
use App\Models\UnitTranslation;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;

class ImportUnitTranslationsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureUnitTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $unitId = (int) ($item['unit_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));
            $description = trim((string) ($item['description'] ?? $item['text'] ?? ''));

            if ($unitId <= 0 || $locale === '' || ($name === '' && $description === '')) {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if ($name !== '' && mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    "items.{$index}.name" => [__('locations.units.errors.translation_import_name_too_long')],
                ]);
            }

            if ($description !== '' && mb_strlen($description) > TextDescriptionLimits::TRANSLATION_MAX) {
                throw ValidationException::withMessages([
                    "items.{$index}.description" => [__('issues.errors.translation_import_too_long')],
                ]);
            }

            $unit = Unit::query()->find($unitId);

            if ($unit === null || ! $unit->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_id" => [__('locations.units.errors.translation_import_missing')],
                ]);
            }

            if ($locale === $unit->normalizedOriginalLanguage()) {
                continue;
            }

            $this->ensureSlots->handle($unit);

            $row = UnitTranslation::query()
                ->where('unit_id', $unit->id)
                ->where('locale', $locale)
                ->firstOrFail();

            $nextName = $name !== '' ? $name : null;
            $nextDescription = $description !== '' ? $description : null;

            if (
                $row->status === UnitTranslationStatus::Completed
                && $row->name === $nextName
                && $row->description === $nextDescription
            ) {
                continue;
            }

            $row->fill([
                'name' => $nextName,
                'description' => $nextDescription,
                'status' => UnitTranslationStatus::Completed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $unit->tenant_id,
                'unit.translation_imported',
                UnitTranslation::class,
                (int) $row->id,
                [
                    'unit_id' => $unit->id,
                    'locale' => $locale,
                ],
            );

            UnitTranslationImported::dispatch($row, $actorUserId);

            $imported++;
        }

        return $imported;
    }
}
