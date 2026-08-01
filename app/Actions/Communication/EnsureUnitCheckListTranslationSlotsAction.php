<?php

namespace App\Actions\Communication;

use App\Enums\UnitCheckListTranslationStatus;
use App\Models\UnitCheckList;
use App\Models\UnitCheckListTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureUnitCheckListTranslationSlotsAction
{
    public function handle(UnitCheckList $list): void
    {
        if (! $list->is_active) {
            return;
        }

        foreach (LocaleSupport::targetLocalesForSource($list->original_language) as $locale) {
            $row = UnitCheckListTranslation::firstOrCreate(
                [
                    'unit_check_list_id' => $list->id,
                    'locale' => $locale,
                ],
                [
                    'status' => UnitCheckListTranslationStatus::Pending,
                ],
            );

            // Self-heal old failed rows (empty value) so export can retry.
            if (
                $row->status === UnitCheckListTranslationStatus::Failed
                && blank($row->name)
            ) {
                $row->fill([
                    'status' => UnitCheckListTranslationStatus::Pending,
                ])->save();
            }
        }
    }
}
