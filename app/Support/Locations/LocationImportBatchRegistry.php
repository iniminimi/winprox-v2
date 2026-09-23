<?php

declare(strict_types=1);

namespace App\Support\Locations;

use App\Models\AuditLog;
use App\Models\Location;
use Illuminate\Support\Collection;

final class LocationImportBatchRegistry
{
    public const RECENT_BATCH_LIMIT = 10;

    public const RECENT_BATCH_DAYS = 30;

    /**
     * @return Collection<int, array{batch_id: string, created_at: \Carbon\Carbon, location_count: int, file_name: string|null}>
     */
    public static function recentBatchesForTenant(int $tenantId): Collection
    {
        return AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('action', 'locations.import')
            ->where('created_at', '>=', now()->subDays(self::RECENT_BATCH_DAYS))
            ->orderByDesc('id')
            ->limit(self::RECENT_BATCH_LIMIT)
            ->get()
            ->map(function (AuditLog $log) use ($tenantId) {
                $payload = self::payloadArray($log->payload);
                $batchId = $payload['batch_id'] ?? null;

                if ($batchId === null) {
                    return null;
                }

                $locationCount = Location::query()
                    ->where('tenant_id', $tenantId)
                    ->where('import_batch_id', $batchId)
                    ->count();

                if ($locationCount === 0) {
                    return null;
                }

                return [
                    'batch_id' => $batchId,
                    'created_at' => \Carbon\Carbon::parse($log->created_at),
                    'location_count' => $locationCount,
                    'file_name' => $payload['file_name'] ?? null,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{total: int, deletable: int, blocked: int, can_delete: bool}
     */
    public static function summary(int $tenantId, string $batchId): array
    {
        $query = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('import_batch_id', $batchId);

        $total = (clone $query)->count();

        $deletable = (clone $query)
            ->whereDoesntHave('units')
            ->whereDoesntHave('issues')
            ->whereDoesntHave('documents')
            ->whereDoesntHave('announcements')
            ->whereDoesntHave('bulkBatches')
            ->count();

        return [
            'total' => $total,
            'deletable' => $deletable,
            'blocked' => max(0, $total - $deletable),
            'can_delete' => $deletable > 0,
        ];
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
