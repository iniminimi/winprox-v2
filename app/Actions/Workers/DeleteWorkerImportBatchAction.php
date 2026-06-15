<?php

namespace App\Actions\Workers;

use App\Actions\Team\DeleteTeamAction;
use App\Actions\Team\DeleteWorkerAction;
use App\Data\Workers\DeleteWorkerImportBatchData;
use App\Models\AuditLog;
use App\Models\InternalTeam;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteWorkerImportBatchAction
{
    public function __construct(
        private AuditRecorder $audit,
        private DeleteWorkerAction $deleteWorker,
        private DeleteTeamAction $deleteTeam,
    ) {}

    /**
     * @return array{success: bool, deleted_count?: int, deleted_team_count?: int, preserved_count?: int, total_count?: int, errors?: list<string>}
     */
    public function handle(DeleteWorkerImportBatchData $data, int $tenantId, ?int $actorUserId = null): array
    {
        DB::beginTransaction();
        try {
            $allWorkers = Worker::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('import_batch_id', $data->importBatchId)
                ->get();

            $totalCount = $allWorkers->count();

            if ($totalCount === 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'errors'  => ['Geen uitvoerders gevonden voor deze import batch.'],
                ];
            }

            $teamIdsFromBatch = $allWorkers->pluck('internal_team_id')->unique()->filter()->values()->all();
            $teamsEligibleForCleanup = $this->teamIdsWithOnlyBatchWorkers($tenantId, $data->importBatchId, $teamIdsFromBatch);
            $createdTeamIds = $this->createdTeamIdsFromImportAudit($tenantId, $data->importBatchId);

            $workersToDelete = Worker::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('import_batch_id', $data->importBatchId)
                ->whereDoesntHave('devices')
                ->get();

            $deletedCount = 0;

            foreach ($workersToDelete as $worker) {
                $this->deleteWorker->handle($worker, $actorUserId);
                $deletedCount++;
            }

            $preservedCount = $totalCount - $deletedCount;
            $deletedTeamCount = $this->deleteEmptyImportTeams(
                $tenantId,
                $createdTeamIds !== [] ? $createdTeamIds : $teamsEligibleForCleanup,
                $actorUserId,
            );

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'workers.delete_import_batch',
                modelType: Worker::class,
                modelId: null,
                payload: [
                    'batch_id'           => $data->importBatchId,
                    'deleted_count'      => $deletedCount,
                    'deleted_team_count' => $deletedTeamCount,
                    'preserved_count'    => $preservedCount,
                    'preserved_reason'   => 'has_devices',
                ],
            );

            DB::commit();

            return [
                'success'            => true,
                'deleted_count'      => $deletedCount,
                'deleted_team_count' => $deletedTeamCount,
                'preserved_count'  => $preservedCount,
                'total_count'      => $totalCount,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'errors'  => ['Er is een databasefout opgetreden tijdens het verwijderen: '.$e->getMessage()],
            ];
        }
    }

    /**
     * @param  list<int>  $teamIds
     * @return list<int>
     */
    private function teamIdsWithOnlyBatchWorkers(int $tenantId, string $batchId, array $teamIds): array
    {
        $eligible = [];

        foreach ($teamIds as $teamId) {
            $hasNonBatchWorkers = Worker::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('internal_team_id', $teamId)
                ->where(function ($query) use ($batchId) {
                    $query->where('import_batch_id', '!=', $batchId)
                        ->orWhereNull('import_batch_id');
                })
                ->exists();

            if (! $hasNonBatchWorkers) {
                $eligible[] = (int) $teamId;
            }
        }

        return $eligible;
    }

    /**
     * @return list<int>
     */
    private function createdTeamIdsFromImportAudit(int $tenantId, string $batchId): array
    {
        $log = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('action', 'workers.import')
            ->orderByDesc('id')
            ->get()
            ->first(function (AuditLog $entry) use ($batchId) {
                $payload = is_array($entry->payload)
                    ? $entry->payload
                    : (is_string($entry->payload) ? json_decode($entry->payload, true) : []);

                return is_array($payload) && ($payload['batch_id'] ?? null) === $batchId;
            });

        if ($log === null) {
            return [];
        }

        $payload = is_array($log->payload)
            ? $log->payload
            : (is_string($log->payload) ? json_decode($log->payload, true) : []);

        if (! is_array($payload)) {
            return [];
        }

        $teamIds = $payload['created_team_ids'] ?? [];

        return array_values(array_map('intval', array_filter($teamIds, fn ($id) => is_numeric($id))));
    }

    /**
     * @param  list<int>  $teamIds
     */
    private function deleteEmptyImportTeams(int $tenantId, array $teamIds, ?int $actorUserId): int
    {
        $deletedTeamCount = 0;

        foreach ($teamIds as $teamId) {
            $team = InternalTeam::query()
                ->where('tenant_id', $tenantId)
                ->find($teamId);

            if ($team === null || $team->workers()->exists() || $team->tasks()->exists()) {
                continue;
            }

            try {
                $this->deleteTeam->handle($team, $actorUserId);
                $deletedTeamCount++;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $deletedTeamCount;
    }
}
