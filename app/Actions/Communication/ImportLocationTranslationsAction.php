<?php

namespace App\Actions\Communication;

use App\Enums\LocationTranslationStatus;
use App\Events\Locations\LocationTranslationImported;
use App\Models\Location;
use App\Models\LocationTranslation;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Validation\ValidationException;

class ImportLocationTranslationsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureLocationTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $locationId = (int) ($item['location_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));

            if ($locationId <= 0 || $locale === '' || $name === '') {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if (mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    "items.{$index}.name" => [__('locations.errors.translation_import_name_too_long')],
                ]);
            }

            $location = Location::query()->find($locationId);

            if ($location === null || ! $location->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.location_id" => [__('locations.errors.translation_import_missing')],
                ]);
            }

            if ($locale === $location->normalizedOriginalLanguage()) {
                continue;
            }

            $this->ensureSlots->handle($location);

            $row = LocationTranslation::query()
                ->where('location_id', $location->id)
                ->where('locale', $locale)
                ->firstOrFail();

            if (
                $row->status === LocationTranslationStatus::Completed
                && $row->name === $name
            ) {
                continue;
            }

            $row->fill([
                'name' => $name,
                'status' => LocationTranslationStatus::Completed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $location->tenant_id,
                'location.translation_imported',
                LocationTranslation::class,
                (int) $row->id,
                [
                    'location_id' => $location->id,
                    'locale' => $locale,
                ],
            );

            LocationTranslationImported::dispatch($row, $actorUserId);

            $imported++;
        }

        return $imported;
    }
}
