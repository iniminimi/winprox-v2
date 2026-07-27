<?php

namespace App\Actions\Communication;

use App\Enums\InternalTeamTranslationStatus;
use App\Models\InternalTeam;
use App\Models\InternalTeamTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Validation\ValidationException;

class TranslateInternalTeamAction
{
    public function __construct(
        private TranslationProviderInterface $translator,
        private AuditRecorder $audit,
        private EnsureInternalTeamTranslationSlotsAction $ensureSlots,
    ) {}

    public function handle(InternalTeam $team, string $targetLocale, ?int $actorUserId = null): InternalTeamTranslation
    {
        if (! $team->is_active) {
            throw ValidationException::withMessages([
                'team' => [__('team.errors.translation_requires_active')],
            ]);
        }

        $targetLocale = LocaleSupport::normalize($targetLocale);

        if ($targetLocale === $team->normalizedOriginalLanguage()) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($team);

        $row = InternalTeamTranslation::query()
            ->where('internal_team_id', $team->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if ($row->status === InternalTeamTranslationStatus::Completed && filled($row->name)) {
            return $row;
        }

        $sourceName = trim((string) $team->name);
        $translatedName = $sourceName !== ''
            ? trim($this->translator->translate($sourceName, $targetLocale))
            : '';

        $failed = $sourceName === ''
            || $translatedName === ''
            || $translatedName === $sourceName
            || mb_strlen($translatedName) > 255;

        if ($failed) {
            $row->fill([
                'name' => null,
                'status' => InternalTeamTranslationStatus::Failed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $team->tenant_id,
                'team.translation_stored',
                InternalTeamTranslation::class,
                (int) $row->id,
                [
                    'internal_team_id' => $team->id,
                    'locale' => $targetLocale,
                    'status' => InternalTeamTranslationStatus::Failed->value,
                ],
            );

            return $row->fresh();
        }

        $row->fill([
            'name' => $translatedName,
            'status' => InternalTeamTranslationStatus::Completed,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $team->tenant_id,
            'team.translation_stored',
            InternalTeamTranslation::class,
            (int) $row->id,
            [
                'internal_team_id' => $team->id,
                'locale' => $targetLocale,
                'status' => InternalTeamTranslationStatus::Completed->value,
            ],
        );

        return $row->fresh();
    }
}
