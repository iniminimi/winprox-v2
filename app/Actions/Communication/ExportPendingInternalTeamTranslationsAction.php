<?php

namespace App\Actions\Communication;

use App\Enums\InternalTeamTranslationStatus;
use App\Models\InternalTeamTranslation;

class ExportPendingInternalTeamTranslationsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        return InternalTeamTranslation::query()
            ->where('status', InternalTeamTranslationStatus::Pending)
            ->whereHas('team', fn ($query) => $query
                ->where('is_active', true)
                ->where('name', '!=', ''))
            ->with('team')
            ->orderBy('internal_team_id')
            ->orderBy('locale')
            ->get()
            ->map(function (InternalTeamTranslation $row): array {
                $team = $row->team;

                return [
                    'internal_team_id' => $team->id,
                    'tenant_id' => $team->tenant_id,
                    'source_locale' => $team->normalizedOriginalLanguage(),
                    'source_name' => (string) $team->name,
                    'locale' => $row->locale,
                    'status' => InternalTeamTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();
    }
}
