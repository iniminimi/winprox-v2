<?php

declare(strict_types=1);

namespace App\Support\Workers;

use App\Models\Worker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class WorkerImportBatchRegistry
{
    public const RECENT_BATCH_LIMIT = 10;

    public const RECENT_BATCH_DAYS = 30;

    /**
     * @return Collection<int, array{batch_id: string, created_at: \Carbon\Carbon, worker_count: int, file_name: string|null}>
     */
    public static function recentBatchesForTenant(int $tenantId): Collection
    {
        return DB::table('audit_logs')
            ->where('tenant_id', $tenantId)
            ->where('action', 'workers.import')
            ->where('created_at', '>=', now()->subDays(self::RECENT_BATCH_DAYS))
            ->orderByDesc('id')
            ->limit(self::RECENT_BATCH_LIMIT)
            ->get()
            ->map(function ($log) use ($tenantId) {
                $payload = json_decode($log->payload, true);
                $batchId = $payload['batch_id'] ?? null;

                if ($batchId === null) {
                    return null;
                }

                $workerCount = Worker::where('tenant_id', $tenantId)
                    ->where('import_batch_id', $batchId)
                    ->count();

                return [
                    'batch_id'     => $batchId,
                    'created_at'   => \Carbon\Carbon::parse($log->created_at),
                    'worker_count' => $workerCount,
                    'file_name'    => $payload['file_name'] ?? null,
                ];
            })
            ->filter()
            ->filter(fn ($batch) => $batch['worker_count'] > 0);
    }

    /**
     * @return array{total: int, deletable: int, blocked: int, can_delete: bool}
     */
    public static function summary(int $tenantId, string $batchId): array
    {
        $total = Worker::where('tenant_id', $tenantId)
            ->where('import_batch_id', $batchId)
            ->count();

        $deletable = Worker::where('tenant_id', $tenantId)
            ->where('import_batch_id', $batchId)
            ->whereDoesntHave('devices')
            ->count();

        return [
            'total'      => $total,
            'deletable'  => $deletable,
            'blocked'    => max(0, $total - $deletable),
            'can_delete' => $deletable > 0,
        ];
    }
}
