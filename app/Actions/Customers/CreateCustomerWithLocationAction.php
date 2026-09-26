<?php

namespace App\Actions\Customers;

use App\Actions\Locations\CreateLocationAction;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Worker-flow "nieuwe klant onderweg" (docs/CHECKMATE.md §4.4): één scherm —
 * bestaande klant kiezen óf nieuwe aanmaken + werkadres. GPS-pin van de gsm is
 * verplicht: zonder pin kan geen werkbezoek gestart worden (nabijheidscheck).
 * Checkmate-werkadres krijgt bewust géén site-unit (geen Facility-QR).
 */
class CreateCustomerWithLocationAction
{
    public function __construct(
        private CreateCustomerAction $createCustomer,
        private CreateLocationAction $createLocation,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{customer: Customer, location: Location}
     */
    public function handle(Worker $worker, array $data): array
    {
        $tenant = Tenant::query()->findOrFail((int) $worker->tenant_id);

        $latitude = self::nullableFloat($data['latitude'] ?? null);
        $longitude = self::nullableFloat($data['longitude'] ?? null);
        if ($latitude === null || $longitude === null) {
            throw new InvalidArgumentException('visit_gps_required');
        }
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('visit_gps_invalid');
        }

        return DB::transaction(function () use ($worker, $tenant, $data, $latitude, $longitude): array {
            $customer = $this->resolveCustomer($worker, $tenant, $data);

            $location = $this->createLocation->handle([
                'name' => trim((string) ($data['location_name'] ?? '')) !== ''
                    ? trim((string) $data['location_name'])
                    : $customer->name,
                'street' => $data['street'] ?? null,
                'house_number' => $data['house_number'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'city' => $data['city'] ?? null,
                'country_code' => $data['country_code'] ?? 'BE',
                'contractual_relationship_reference' => $data['contractual_relationship_reference'] ?? null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'customer_id' => (int) $customer->id,
                'with_site_unit' => false,
            ], (int) $tenant->id);

            return ['customer' => $customer, 'location' => $location];
        });
    }

    private function resolveCustomer(Worker $worker, Tenant $tenant, array $data): Customer
    {
        $customerId = $data['customer_id'] ?? null;
        if ($customerId !== null && (int) $customerId > 0) {
            $customer = Customer::query()
                ->where('tenant_id', (int) $tenant->id)
                ->whereKey((int) $customerId)
                ->first();

            if ($customer === null) {
                throw new InvalidArgumentException('customer_not_found');
            }

            return $customer;
        }

        return $this->createCustomer->handle($tenant, [
            'name' => $data['customer_name'] ?? '',
            'contact_name' => $data['contact_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
