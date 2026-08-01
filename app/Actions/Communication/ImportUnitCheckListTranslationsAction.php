<?php

namespace App\Actions\Communication;

use App\Enums\UnitCheckListTranslationStatus;
use App\Models\UnitCheckList;
use App\Models\UnitCheckListTranslation;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Validation\ValidationException;

class ImportUnitCheckListTranslationsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureUnitCheckListTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $listId = (int) ($item['unit_check_list_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));
            $labels = $item['items'] ?? null;

            if ($listId <= 0 || $locale === '' || $name === '') {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if (mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    "items.{$index}.name" => [__('unit_checks.lists.errors.translation_import_name_too_long')],
                ]);
            }

            if ($labels !== null && ! is_array($labels)) {
                throw ValidationException::withMessages([
                    "items.{$index}.items" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if (is_array($labels)) {
                foreach ($labels as $labelIndex => $label) {
                    if (! is_string($label) && ! is_numeric($label)) {
                        throw ValidationException::withMessages([
                            "items.{$index}.items.{$labelIndex}" => [__('issues.errors.translation_import_invalid')],
                        ]);
                    }

                    if (mb_strlen(trim((string) $label)) > 255) {
                        throw ValidationException::withMessages([
                            "items.{$index}.items.{$labelIndex}" => [__('unit_checks.lists.errors.translation_import_item_too_long')],
                        ]);
                    }
                }
            }

            $list = UnitCheckList::query()->find($listId);

            if ($list === null || ! $list->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_check_list_id" => [__('unit_checks.lists.errors.translation_import_missing')],
                ]);
            }

            if ($locale === $list->normalizedOriginalLanguage()) {
                continue;
            }

            $sourceItems = $list->sourceItemLabels();
            $normalizedItems = null;

            if ($sourceItems !== []) {
                if (! is_array($labels) || count($labels) !== count($sourceItems)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.items" => [__('unit_checks.lists.errors.translation_import_items_mismatch')],
                    ]);
                }

                $normalizedItems = array_map(
                    static fn (mixed $label): string => trim((string) $label),
                    $labels,
                );
            }

            $this->ensureSlots->handle($list);

            $row = UnitCheckListTranslation::query()
                ->where('unit_check_list_id', $list->id)
                ->where('locale', $locale)
                ->firstOrFail();

            if (
                $row->status === UnitCheckListTranslationStatus::Completed
                && $row->name === $name
                && $row->items == $normalizedItems
            ) {
                continue;
            }

            $row->fill([
                'name' => $name,
                'items' => $normalizedItems,
                'status' => UnitCheckListTranslationStatus::Completed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $list->tenant_id,
                'unit_check_list.translation_imported',
                UnitCheckListTranslation::class,
                (int) $row->id,
                [
                    'unit_check_list_id' => $list->id,
                    'locale' => $locale,
                ],
            );

            $imported++;
        }

        return $imported;
    }
}
