<?php

namespace App\Actions\Team;

use App\Models\TeamAnnouncement;
use App\Models\TeamDocument;
use App\Models\User;
use App\Models\Worker;
use InvalidArgumentException;

/**
 * Per uitvoerder voor wie het item bedoeld is: geopend ja of nee (zonder tijdstip).
 */
class ListTeamLibraryOpensAction
{
    /**
     * @return list<array{worker: Worker, opened: bool}>
     */
    public function handle(int $tenantId, TeamDocument|TeamAnnouncement $item, ?User $viewer = null): array
    {
        if ((int) $item->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('team_library_tenant_mismatch');
        }

        return Worker::query()
            ->where('tenant_id', $tenantId)
            ->when(
                $item->worker_id !== null,
                fn ($q) => $q->whereKey($item->worker_id),
                fn ($q) => $q->where('internal_team_id', $item->internal_team_id)->where('is_active', true),
            )
            ->when($viewer instanceof User, fn ($query) => $query->visibleTo($viewer))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Worker $worker): array => [
                'worker' => $worker,
                'opened' => $item->isOpenedBy((int) $worker->id),
            ])
            ->sortBy(fn (array $row): int => $row['opened'] ? 1 : 0)
            ->values()
            ->all();
    }
}
