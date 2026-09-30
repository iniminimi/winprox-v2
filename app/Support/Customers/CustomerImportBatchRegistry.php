<?php

declare(strict_types=1);

namespace App\Support\Customers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Location;
use App\Support\Locations\LocationDeletionGuard;
use Illuminate\Support\Collection;

final class CustomerImportBatchRegistry
{
    public const RECENT_BATCH_LIMIT = 10;

    public const RECENT_BATCH_DAYS = 30;

    /**
     * @return Collection<int, array{batch_id: string, created_at: \Carbon\Carbon, customer_count: int, location_count: int, file_name: string|null}>
     */
    public static function recentBatchesForTenant(int $tenantId): Collection
    {
        return AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('action', 'customers.import')
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

                $customerCount = Customer::query()
                    ->where('tenant_id', $tenantId)
                    ->where('import_batch_id', $batchId)
                    ->count();

                $locationCount = Location::query()
                    ->where('tenant_id', $tenantId)
                    ->where('import_batch_id', $batchId)
                    ->count();

                if ($customerCount === 0 && $locationCount === 0) {
                    return null;
                }

                return [
                    'batch_id' => $batchId,
                    'created_at' => \Carbon\Carbon::parse($log->created_at),
                    'customer_count' => $customerCount,
                    'location_count' => $locationCount,
                    'file_name' => $payload['file_name'] ?? null,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{total_customers: int, deletable_customers: int, total_locations: int, deletable_locations: int, blocked: int, can_delete: bool}
     */
    public static function summary(int $tenantId, string $batchId): array
    {
        $locations = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('import_batch_id', $batchId)
            ->get();

        $deletableLocationIds = [];
        foreach ($locations as $location) {
            if (self::locationIsDeletable($location)) {
                $deletableLocationIds[(int) $location->id] = true;
            }
        }

        $customers = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('import_batch_id', $batchId)
            ->with('locations:id,customer_id')
            ->get();

        $deletableCustomers = 0;
        foreach ($customers as $customer) {
            $allLocationsDeletable = $customer->locations->every(
                static fn (Location $location): bool => isset($deletableLocationIds[(int) $location->id]),
            );

            if ($allLocationsDeletable) {
                $deletableCustomers++;
            }
        }

        $deletableLocations = count($deletableLocationIds);

        return [
            'total_customers' => $customers->count(),
            'deletable_customers' => $deletableCustomers,
            'total_locations' => $locations->count(),
            'deletable_locations' => $deletableLocations,
            'blocked' => ($customers->count() - $deletableCustomers) + ($locations->count() - $deletableLocations),
            'can_delete' => $deletableCustomers > 0 || $deletableLocations > 0,
        ];
    }

    /**
     * Zelfde regels als DeleteLocationAction via LocationDeletionGuard:
     * onaangeroerde site-units tellen niet als inhoud, een werkadres met
     * WorkVisits mag een import-undo nooit wissen (CIAO-bewijs).
     */
    public static function locationIsDeletable(Location $location): bool
    {
        return LocationDeletionGuard::canDelete($location);
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
