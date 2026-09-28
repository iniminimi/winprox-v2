<?php

namespace App\Actions\Customers;

use App\Actions\Locations\DeleteLocationAction;
use App\Data\Customers\DeleteCustomerImportBatchData;
use App\Models\Customer;
use App\Models\Location;
use App\Support\Audit\AuditRecorder;
use App\Support\Customers\CustomerImportBatchRegistry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteCustomerImportBatchAction
{
    public function __construct(
        private AuditRecorder $audit,
        private DeleteLocationAction $deleteLocation,
    ) {}

    /**
     * @return array{success: bool, deleted_customers?: int, deleted_locations?: int, preserved_count?: int, errors?: list<string>}
     */
    public function handle(DeleteCustomerImportBatchData $data, int $tenantId, ?int $actorUserId = null): array
    {
        DB::beginTransaction();
        try {
            $locations = Location::query()
                ->where('tenant_id', $tenantId)
                ->where('import_batch_id', $data->importBatchId)
                ->get();

            $customers = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('import_batch_id', $data->importBatchId)
                ->get();

            if ($locations->isEmpty() && $customers->isEmpty()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'errors' => [__('customers.customers_import_history.nothing_deletable')],
                ];
            }

            $deletedLocations = 0;
            $preservedLocations = 0;

            foreach ($locations as $location) {
                if (! CustomerImportBatchRegistry::locationIsDeletable($location)) {
                    $preservedLocations++;

                    continue;
                }

                try {
                    $this->deleteLocation->handle($location, $actorUserId);
                    $deletedLocations++;
                } catch (InvalidArgumentException) {
                    $preservedLocations++;
                }
            }

            $deletedCustomers = 0;
            $preservedCustomers = 0;

            foreach ($customers as $customer) {
                if ($customer->locations()->exists()) {
                    $preservedCustomers++;

                    continue;
                }

                $customerId = (int) $customer->id;
                $customerName = (string) $customer->name;
                $customer->delete();
                $deletedCustomers++;

                $this->audit->record(
                    userId: $actorUserId,
                    tenantId: $tenantId,
                    action: 'customer.deleted',
                    modelType: Customer::class,
                    modelId: $customerId,
                    payload: ['id' => $customerId, 'name' => $customerName],
                );
            }

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'customers.delete_import_batch',
                modelType: Customer::class,
                modelId: null,
                payload: [
                    'batch_id' => $data->importBatchId,
                    'deleted_customers' => $deletedCustomers,
                    'deleted_locations' => $deletedLocations,
                    'preserved_count' => $preservedCustomers + $preservedLocations,
                    'preserved_reason' => 'has_content',
                ],
            );

            DB::commit();

            return [
                'success' => true,
                'deleted_customers' => $deletedCustomers,
                'deleted_locations' => $deletedLocations,
                'preserved_count' => $preservedCustomers + $preservedLocations,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'errors' => [__('customers.customers_import_history.delete_failed')],
            ];
        }
    }
}
