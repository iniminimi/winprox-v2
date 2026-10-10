<?php

namespace App\Actions\Customers;

use App\Actions\Locations\DeleteLocationAction;
use App\Models\Customer;
use App\Support\Audit\AuditRecorder;
use App\Support\Customers\CustomerDeletionGuard;
use InvalidArgumentException;

/**
 * Klant verwijderen — inclusief gekoppelde locaties die nog leeg zijn
 * (alleen onaangeroerde «Hele locatie»-unit). Zelfde invariant als
 * LocationDeletionGuard / import-undo: locaties met bezoeken of inhoud blijven.
 */
class DeleteCustomerAction
{
    public function __construct(
        private AuditRecorder $audit,
        private DeleteLocationAction $deleteLocation,
    ) {}

    public function handle(Customer $customer, ?int $actorUserId = null): void
    {
        if (! CustomerDeletionGuard::canDelete($customer)) {
            throw new InvalidArgumentException('customer_has_locations');
        }

        $locations = $customer->locations()->get();
        foreach ($locations as $location) {
            $this->deleteLocation->handle($location, $actorUserId);
        }

        $customerId = (int) $customer->id;
        $name = (string) $customer->name;
        $tenantId = (int) $customer->tenant_id;

        $customer->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'customer.deleted',
            modelType: Customer::class,
            modelId: $customerId,
            payload: [
                'id' => $customerId,
                'name' => $name,
                'deleted_locations' => $locations->count(),
            ],
        );
    }
}
