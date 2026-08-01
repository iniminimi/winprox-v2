<?php

namespace App\Actions\Communication;

use App\Enums\UnitCheckListTranslationStatus;
use App\Models\UnitCheckList;
use App\Models\UnitCheckListTranslation;
use App\Support\Audit\AuditRecorder;

class InvalidateUnitCheckListTranslationsOnSourceChangeAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  list<string>  $previousItems
     */
    public function handle(
        UnitCheckList $list,
        string $previousName,
        array $previousItems,
        ?int $actorUserId = null,
    ): void {
        $nameChanged = trim($previousName) !== trim((string) $list->name);
        $itemsChanged = $previousItems !== $list->sourceItemLabels();

        if (! $nameChanged && ! $itemsChanged) {
            return;
        }

        if (! $list->is_active) {
            return;
        }

        $source = $list->normalizedOriginalLanguage();

        $invalidated = UnitCheckListTranslation::query()
            ->where('unit_check_list_id', $list->id)
            ->where('locale', '!=', $source)
            ->where(function ($query) {
                $query->where('status', '!=', UnitCheckListTranslationStatus::Pending->value)
                    ->orWhereNotNull('name')
                    ->orWhereNotNull('items');
            })
            ->update([
                'name' => null,
                'items' => null,
                'status' => UnitCheckListTranslationStatus::Pending->value,
            ]);

        if ($invalidated === 0) {
            return;
        }

        $this->audit->record(
            $actorUserId,
            (int) $list->tenant_id,
            'unit_check_list.translations_invalidated',
            UnitCheckList::class,
            (int) $list->id,
            [
                'unit_check_list_id' => $list->id,
                'rows' => $invalidated,
                'source_locale' => $source,
            ],
        );
    }
}
