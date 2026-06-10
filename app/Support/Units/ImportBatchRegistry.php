<?php

declare(strict_types=1);

namespace App\Support\Units;

use App\Models\AuditLog;
use App\Models\Unit;
use Illuminate\Support\Collection;

final class ImportBatchRegistry
{
    public const RECENT_BATCH_LIMIT = 10;

    public const RECENT_BATCH_DAYS = 30;

    /**
     * Get recent import batches for a tenant.
     *
     * @return Collection<int, array{batch_id: string, created_at: \Carbon\Carbon, unit_count: int, file_name: string|null}>
     */
    public static function recentBatchesForTenant(int $tenantId): Collection
    {
        return AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('action', 'units.import')
            ->where('created_at', '>=', now()->subDays(self::RECENT_BATCH_DAYS))
            ->orderByDesc('id')
            ->limit(self::RECENT_BATCH_LIMIT)
            ->get()
            ->map(function (AuditLog $log) use ($tenantId) {
                $payload = self::payloadArray($log->payload);
                $batchId = $payload['batch_id'] ?? null;

                // Skip logs without batch_id (old imports before this feature)
                if ($batchId === null) {
                    return null;
                }

                // Get actual unit count for this batch
                $unitCount = Unit::where('tenant_id', $tenantId)
                    ->where('import_batch_id', $batchId)
                    ->count();

                return [
                    'batch_id' => $batchId,
                    'created_at' => \Carbon\Carbon::parse($log->created_at),
                    'unit_count' => $unitCount,
                    'file_name' => $payload['file_name'] ?? null,
                ];
            })
            ->filter()
            ->filter(fn ($batch) => $batch['unit_count'] > 0);
    }

    /**
     * Get summary for a specific batch.
     *
     * @return array{total: int, deletable: int, blocked: int, can_delete: bool}
     */
    public static function summary(int $tenantId, string $batchId): array
    {
        $total = Unit::where('tenant_id', $tenantId)
            ->where('import_batch_id', $batchId)
            ->count();

        $deletable = Unit::where('tenant_id', $tenantId)
            ->where('import_batch_id', $batchId)
            ->whereDoesntHave('issues')
            ->whereDoesntHave('issues.tasks')
            ->count();

        return [
            'total' => $total,
            'deletable' => $deletable,
            'blocked' => max(0, $total - $deletable),
            'can_delete' => $deletable > 0,
        ];
    }

    /**
     * Check if a batch can be deleted.
     */
    public static function canDelete(int $tenantId, string $batchId): bool
    {
        return self::summary($tenantId, $batchId)['can_delete'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadArray(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload)) {
            return json_decode($payload, true) ?? [];
        }

        return [];
    }
}
