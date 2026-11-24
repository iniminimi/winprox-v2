<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HardwareDeviceCondition;
use App\Enums\HardwareDeviceStatus;
use App\Enums\HardwareDeviceType;
use App\Models\HardwareDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HardwareDevice>
 */
class HardwareDeviceFactory extends Factory
{
    protected $model = HardwareDevice::class;

    public function definition(): array
    {
        return [
            'brand' => 'Seeed',
            'type' => HardwareDeviceType::ClockDisplay->value,
            'model' => 'Indicator D1',
            'sku' => null,
            'serial_number' => (string) fake()->unique()->numerify('##############'),
            'mac' => null,
            'tenant_id' => null,
            'clock_point_id' => null,
            'status' => HardwareDeviceStatus::InStock->value,
            'condition' => HardwareDeviceCondition::New->value,
            'sn_scanned_at' => now(),
            'mac_set_at' => null,
            'assigned_to_tenant_at' => null,
            'installed_at' => null,
            'last_seen_at' => null,
            'returned_at' => null,
            'retired_at' => null,
            'purchase_ref' => null,
            'purchased_at' => null,
            'notes' => null,
        ];
    }

    public function withMac(?string $mac = null): static
    {
        $value = $mac ?? strtoupper(bin2hex(random_bytes(6)));

        return $this->state(fn () => [
            'mac' => $value,
            'mac_set_at' => now(),
        ]);
    }
}
