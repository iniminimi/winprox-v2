<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerWeekdayMinute;

/**
 * Eenmalige, idempotente aanvulling: uitvoerders zonder weekpatroon
 * krijgen de standaardwerkregeling (contract/5 over ma–vr). Bestaande
 * patronen — ook onvolledige — blijven ongemoeid. Een ontbrekende
 * contractnorm wordt 2280 (38u) gezet.
 */
class BackfillWorkerWeekPatternsAction
{
    public function __construct(private SyncWorkerWeekdayMinutesAction $sync) {}

    /**
     * @return array{
     *     workers: int,
     *     already_patterned: int,
     *     seeded: int,
     *     seeded_deviating: int,
     *     contract_filled: int,
     *     incomplete_patterns: int,
     *     deviating_workers: list<array{id: int, name: string, contract_minutes: int}>,
     *     incomplete_workers: list<array{id: int, name: string, rows: int}>,
     * }
     */
    public function handle(Tenant $tenant, bool $dryRun = false): array
    {
        $result = [
            'workers' => 0,
            'already_patterned' => 0,
            'seeded' => 0,
            'seeded_deviating' => 0,
            'contract_filled' => 0,
            'incomplete_patterns' => 0,
            'deviating_workers' => [],
            'incomplete_workers' => [],
        ];

        if (! $tenant->hasTimeModule()) {
            return $result;
        }

        $rowCounts = WorkerWeekdayMinute::query()
            ->where('tenant_id', $tenant->id)
            ->selectRaw('worker_id, count(*) as row_count')
            ->groupBy('worker_id')
            ->pluck('row_count', 'worker_id');

        $workers = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'weekly_contract_minutes']);

        $result['workers'] = $workers->count();

        foreach ($workers as $worker) {
            $rowCount = (int) ($rowCounts[$worker->id] ?? 0);
            if ($rowCount === 7) {
                $result['already_patterned']++;

                continue;
            }
            if ($rowCount > 0) {
                $result['incomplete_patterns']++;
                $result['incomplete_workers'][] = [
                    'id' => (int) $worker->id,
                    'name' => trim("{$worker->first_name} {$worker->last_name}"),
                    'rows' => $rowCount,
                ];

                continue;
            }

            $contract = $worker->weekly_contract_minutes === null
                ? Worker::DEFAULT_WEEKLY_CONTRACT_MINUTES
                : (int) $worker->weekly_contract_minutes;

            if ($contract !== Worker::DEFAULT_WEEKLY_CONTRACT_MINUTES) {
                $result['seeded_deviating']++;
                $result['deviating_workers'][] = [
                    'id' => (int) $worker->id,
                    'name' => trim("{$worker->first_name} {$worker->last_name}"),
                    'contract_minutes' => $contract,
                ];
            } else {
                $result['seeded']++;
            }
            if ($worker->weekly_contract_minutes === null) {
                $result['contract_filled']++;
            }

            if ($dryRun) {
                continue;
            }

            if ($worker->weekly_contract_minutes === null) {
                $worker->forceFill(['weekly_contract_minutes' => $contract])->save();
            }
            $this->sync->handle((int) $tenant->id, $worker->fresh(), Worker::defaultWeekPattern($contract));
        }

        return $result;
    }
}
