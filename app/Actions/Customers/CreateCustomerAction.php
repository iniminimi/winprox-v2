<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class CreateCustomerAction
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, array $data, ?int $actorUserId = null): Customer
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('customer_name_required');
        }

        $customer = Customer::create([
            'tenant_id' => (int) $tenant->id,
            'name' => $name,
            'contact_name' => self::nullableString($data['contact_name'] ?? null),
            'email' => self::nullableString($data['email'] ?? null),
            'phone' => self::nullableString($data['phone'] ?? null),
            'is_active' => true,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'customer.created',
            modelType: Customer::class,
            modelId: (int) $customer->id,
            payload: ['id' => $customer->id, 'name' => $customer->name],
        );

        return $customer;
    }

    private static function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
