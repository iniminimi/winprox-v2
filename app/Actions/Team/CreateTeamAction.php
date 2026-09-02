<?php

namespace App\Actions\Team;

use App\Actions\Communication\EnsureInternalTeamTranslationSlotsAction;
use App\Models\InternalTeam;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;

/**
 * Maakt een operationeel team aan.
 *
 * Integration-first (§3.0): tenant wordt expliciet meegegeven, niet via globale
 * state — identiek aanroepbaar door Livewire, API, CLI, job.
 */
class CreateTeamAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureInternalTeamTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $tenantId, ?int $actorUserId = null): InternalTeam
    {
        $team = InternalTeam::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'original_language' => LocaleSupport::normalize($data['original_language'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'clocks_all_locations' => (bool) ($data['clocks_all_locations'] ?? false),
            'session_lifespan_hours' => $data['session_lifespan_hours'] ?? null,
        ]);

        $this->ensureSlots->handle($team);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'team.created',
            modelType: InternalTeam::class,
            modelId: (int) $team->id,
            payload: ['id' => $team->id, 'name' => $team->name],
        );

        return $team;
    }
}
