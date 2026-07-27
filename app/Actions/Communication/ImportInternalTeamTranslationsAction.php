<?php

namespace App\Actions\Communication;

use App\Enums\InternalTeamTranslationStatus;
use App\Models\InternalTeam;
use App\Models\InternalTeamTranslation;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Validation\ValidationException;

class ImportInternalTeamTranslationsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureInternalTeamTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $teamId = (int) ($item['internal_team_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));

            if ($teamId <= 0 || $locale === '' || $name === '') {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if (mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    "items.{$index}.name" => [__('locations.errors.translation_import_name_too_long')],
                ]);
            }

            $team = InternalTeam::query()->find($teamId);

            if ($team === null || ! $team->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.internal_team_id" => [__('locations.errors.translation_import_missing')],
                ]);
            }

            if ($locale === $team->normalizedOriginalLanguage()) {
                continue;
            }

            $this->ensureSlots->handle($team);

            $row = InternalTeamTranslation::query()
                ->where('internal_team_id', $team->id)
                ->where('locale', $locale)
                ->firstOrFail();

            if (
                $row->status === InternalTeamTranslationStatus::Completed
                && $row->name === $name
            ) {
                continue;
            }

            $row->fill([
                'name' => $name,
                'status' => InternalTeamTranslationStatus::Completed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $team->tenant_id,
                'team.translation_imported',
                InternalTeamTranslation::class,
                (int) $row->id,
                [
                    'internal_team_id' => $team->id,
                    'locale' => $locale,
                ],
            );

            $imported++;
        }

        return $imported;
    }
}
