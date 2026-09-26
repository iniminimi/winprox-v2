<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class UpdateCustomerAction
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Customer $customer, array $data, ?int $actorUserId = null): Customer
    {
        $updates = [];

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new InvalidArgumentException('customer_name_required');
            }
            $updates['name'] = $name;
        }

        foreach (['contact_name', 'email', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = self::nullableString($data[$field]);
            }
        }

        if (array_key_exists('is_active', $data)) {
            $updates['is_active'] = (bool) $data['is_active'];
        }

        if ($updates !== []) {
            $customer->update($updates);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: (int) $customer->tenant_id,
                action: 'customer.updated',
                modelType: Customer::class,
                modelId: (int) $customer->id,
                payload: ['id' => $customer->id, 'name' => $customer->fresh()->name],
            );
        }

        return $customer->fresh();
    }

    private static function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
