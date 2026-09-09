<?php

namespace App\Actions\Team;

use App\Actions\Communication\EnsureInternalTeamTranslationSlotsAction;
use App\Actions\Communication\InvalidateInternalTeamTranslationsOnSourceChangeAction;
use App\Models\InternalTeam;
use App\Support\Audit\AuditRecorder;

class UpdateTeamAction
{
    public function __construct(
        private AuditRecorder $audit,
        private InvalidateInternalTeamTranslationsOnSourceChangeAction $invalidateTranslations,
        private EnsureInternalTeamTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(InternalTeam $team, array $data, ?int $actorUserId = null): InternalTeam
    {
        $previousName = (string) $team->name;

        $team->update([
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? $team->is_active),
            'clocks_all_locations' => (bool) ($data['clocks_all_locations'] ?? $team->clocks_all_locations),
            'required_break_minutes' => array_key_exists('required_break_minutes', $data)
                ? self::normalizeRequiredBreakMinutes($data['required_break_minutes'])
                : $team->required_break_minutes,
            'session_lifespan_hours' => $data['session_lifespan_hours'] ?? null,
        ]);

        $fresh = $team->fresh();

        $this->invalidateTranslations->handle($fresh, $previousName, $actorUserId);
        $this->ensureSlots->handle($fresh);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'team.updated',
            modelType: InternalTeam::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'name' => $fresh->name, 'is_active' => $fresh->is_active],
        );

        return $fresh;
    }

    private static function normalizeRequiredBreakMinutes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $minutes = (int) $value;

        return $minutes > 0 ? $minutes : null;
    }
}
