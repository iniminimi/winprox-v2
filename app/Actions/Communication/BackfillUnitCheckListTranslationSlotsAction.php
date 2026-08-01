<?php

namespace App\Actions\Communication;

use App\Models\UnitCheckList;
use App\Models\UnitCheckListTranslation;

class BackfillUnitCheckListTranslationSlotsAction
{
    public function __construct(private EnsureUnitCheckListTranslationSlotsAction $ensureSlots) {}

    /**
     * @return array{lists: int, slots_created: int}
     */
    public function handle(?int $tenantId = null): array
    {
        $listsProcessed = 0;
        $slotsCreated = 0;

        UnitCheckList::query()
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($lists) use (&$listsProcessed, &$slotsCreated): void {
                foreach ($lists as $list) {
                    $before = UnitCheckListTranslation::query()
                        ->where('unit_check_list_id', $list->id)
                        ->count();

                    $this->ensureSlots->handle($list);

                    $after = UnitCheckListTranslation::query()
                        ->where('unit_check_list_id', $list->id)
                        ->count();

                    $listsProcessed++;
                    $slotsCreated += max(0, $after - $before);
                }
            });

        return [
            'lists' => $listsProcessed,
            'slots_created' => $slotsCreated,
        ];
    }
}
