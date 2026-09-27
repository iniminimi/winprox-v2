<?php

namespace Database\Factories;

use App\Enums\ClockDisplayClaimStatus;
use App\Models\ClockDisplayClaim;
use App\Models\ClockPoint;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockDisplayClaim>
 */
class ClockDisplayClaimFactory extends Factory
{
    protected $model = ClockDisplayClaim::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'clock_point_id' => ClockPoint::factory(),
            'claim_token' => Str::lower(Str::random(40)),
            'pairing_code' => 'TESTCODE',
            'device_hint' => 'esp32-'.Str::lower(Str::random(6)),
            'ip' => fake()->ipv4(),
            'status' => ClockDisplayClaimStatus::Pending->value,
            'expires_at' => now()->addMinutes(10),
        ];
    }

    public function forPoint(ClockPoint $clockPoint): static
    {
        return $this->state(fn () => [
            'tenant_id' => $clockPoint->tenant_id,
            'clock_point_id' => $clockPoint->id,
        ]);
    }
}
