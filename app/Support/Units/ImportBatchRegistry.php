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
     * Get recent import batches for a location.
     *
     * @return Collection<int, array{batch_id: string, created_at: \Carbon\Carbon, unit_count: int, file_name: string|null}>
     */
    public static function recentBatchesForLocation(int $tenantId, int $locationId): Collection
    {
        return AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('action', 'units.import')
            ->where('created_at', '>=', now()->subDays(self::RECENT_BATCH_DAYS))
            ->orderByDesc('id')
            ->limit(self::RECENT_BATCH_LIMIT * 3)
            ->get()
            ->map(function (AuditLog $log) use ($tenantId, $locationId) {
                $payload = self::payloadArray($log->payload);
                $batchId = $payload['batch_id'] ?? null;

                if ($batchId === null) {
                    return null;
                }

                $payloadLocationId = isset($payload['location_id']) ? (int) $payload['location_id'] : null;
                if ($payloadLocationId !== null && $payloadLocationId !== $locationId) {
                    return null;
                }

                $unitCount = Unit::where('tenant_id', $tenantId)
                    ->where('location_id', $locationId)
                    ->where('import_batch_id', $batchId)
                    ->count();

                if ($unitCount === 0) {
                    return null;
                }

                // Legacy org-wide batches without location_id: only show if units remain on this location.
                if ($payloadLocationId === null) {
                    $otherLocationUnits = Unit::where('tenant_id', $tenantId)
                        ->where('import_batch_id', $batchId)
                        ->where('location_id', '!=', $locationId)
                        ->exists();

                    if ($otherLocationUnits) {
                        return null;
                    }
                }

                return [
                    'batch_id' => $batchId,
                    'created_at' => \Carbon\Carbon::parse($log->created_at),
                    'unit_count' => $unitCount,
                    'file_name' => $payload['file_name'] ?? null,
                ];
            })
            ->filter()
            ->take(self::RECENT_BATCH_LIMIT)
            ->values();
    }

    /**
     * Get summary for a specific batch (optionally scoped to one location).
     *
     * @return array{total: int, deletable: int, blocked: int, can_delete: bool}
     */
    public static function summary(int $tenantId, string $batchId, ?int $locationId = null): array
    {
        $query = Unit::where('tenant_id', $tenantId)
            ->where('import_batch_id', $batchId)
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId));

        $total = (clone $query)->count();

        $deletable = (clone $query)
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
    public static function canDelete(int $tenantId, string $batchId, ?int $locationId = null): bool
    {
        return self::summary($tenantId, $batchId, $locationId)['can_delete'];
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
