<?php

namespace App\Actions\Communication;

use App\Enums\InternalTeamTranslationStatus;
use App\Models\InternalTeam;
use App\Models\InternalTeamTranslation;
use App\Support\Audit\AuditRecorder;

class InvalidateInternalTeamTranslationsOnSourceChangeAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(InternalTeam $team, string $previousName, ?int $actorUserId = null): void
    {
        if (trim($previousName) === trim((string) $team->name)) {
            return;
        }

        if (! $team->is_active || trim((string) $team->name) === '') {
            return;
        }

        $source = $team->normalizedOriginalLanguage();

        $invalidated = InternalTeamTranslation::query()
            ->where('internal_team_id', $team->id)
            ->where('locale', '!=', $source)
            ->where(function ($query) {
                $query->where('status', '!=', InternalTeamTranslationStatus::Pending->value)
                    ->orWhereNotNull('name');
            })
            ->update([
                'name' => null,
                'status' => InternalTeamTranslationStatus::Pending->value,
            ]);

        if ($invalidated === 0) {
            return;
        }

        $this->audit->record(
            $actorUserId,
            (int) $team->tenant_id,
            'team.translations_invalidated',
            InternalTeam::class,
            (int) $team->id,
            [
                'internal_team_id' => $team->id,
                'rows' => $invalidated,
                'source_locale' => $source,
            ],
        );
    }
}
