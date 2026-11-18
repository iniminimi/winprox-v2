<?php

namespace App\Actions\Team;

use App\Enums\TeamLibraryKind;
use App\Models\TeamAnnouncement;
use App\Models\TeamDocument;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Zet de uitvoerder op de lijst "geopend" van documenten of mededelingen die hij mag zien.
 * Bewust zonder tijdstip: geen audit-rij en `updated_at` blijft ongewijzigd.
 */
class MarkTeamLibraryOpenedAction
{
    /**
     * @param  list<int>|null  $itemIds  null = alle items van deze soort die hij nu ziet
     * @return list<int> id's die hij nog niet geopend had
     */
    public function handle(Worker $actor, int $tenantId, TeamLibraryKind $kind, ?array $itemIds = null): array
    {
        if ((int) $actor->tenant_id !== $tenantId || ! $actor->is_active) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $modelClass = $kind->modelClass();

        return DB::transaction(function () use ($actor, $tenantId, $modelClass, $itemIds): array {
            $newlyOpened = [];

            $modelClass::query()
                ->where('tenant_id', $tenantId)
                ->visibleToWorker($actor)
                ->when($itemIds !== null, fn ($q) => $q->whereKey($itemIds))
                ->lockForUpdate()
                ->get()
                ->each(function (TeamDocument|TeamAnnouncement $item) use ($actor, &$newlyOpened): void {
                    if ($item->isOpenedBy((int) $actor->id)) {
                        return;
                    }

                    $ids = [...$item->openedWorkerIds(), (int) $actor->id];
                    $item->newQuery()->toBase()
                        ->where('id', $item->id)
                        ->update(['opened_worker_ids' => json_encode($ids)]);
                    $newlyOpened[] = (int) $item->id;
                });

            return $newlyOpened;
        });
    }
}
