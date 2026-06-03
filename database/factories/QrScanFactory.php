<?php

namespace Database\Factories;

use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QrScan> */
class QrScanFactory extends Factory
{
    protected $model = QrScan::class;

    public function definition(): array
    {
        return [
            'qr_code_id' => QrCode::factory(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'scanned_at' => now(),
        ];
    }

    public function anonymous(): static
    {
        return $this->state([
            'user_id' => null,
        ]);
    }
}
