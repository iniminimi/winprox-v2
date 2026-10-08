<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

/**
 * Klant verwijderen — enkel zolang er geen werkadressen (Locations) aan
 * hangen. Zelfde invariant als de import-undo: een klant met inhoud blijft
 * staan; deactiveren is dan het alternatief.
 */
class DeleteCustomerAction
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    public function handle(Customer $customer, ?int $actorUserId = null): void
    {
        if ($customer->locations()->exists()) {
            throw new InvalidArgumentException('customer_has_locations');
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
            payload: ['id' => $customerId, 'name' => $name],
        );
    }
}
