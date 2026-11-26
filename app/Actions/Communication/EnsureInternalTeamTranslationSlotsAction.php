<?php

namespace App\Actions\Communication;

use App\Models\Tenant;
use App\Enums\InternalTeamTranslationStatus;
use App\Models\InternalTeam;
use App\Models\InternalTeamTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureInternalTeamTranslationSlotsAction
{
    public function handle(InternalTeam $team): void
    {
        if (! Tenant::translationsEnabledFor((int) $team->tenant_id)) {
            return;
        }

        if (! $team->is_active || trim((string) $team->name) === '') {
            return;
        }

        foreach (LocaleSupport::targetLocalesForSource($team->original_language) as $locale) {
            $row = InternalTeamTranslation::firstOrCreate(
                [
                    'internal_team_id' => $team->id,
                    'locale' => $locale,
                ],
                [
                    'status' => InternalTeamTranslationStatus::Pending,
                ],
            );

            // Self-heal old failed rows (empty value) so export can retry.
            if (
                $row->status === InternalTeamTranslationStatus::Failed
                && blank($row->name)
            ) {
                $row->fill([
                    'status' => InternalTeamTranslationStatus::Pending,
                ])->save();
            }
        }
    }
}
