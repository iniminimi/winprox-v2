<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitGpsReport;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitGpsReport> */
class UnitGpsReportFactory extends Factory
{
    protected $model = UnitGpsReport::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'reported_at' => now(),
            'worker_id' => null,
        ];
    }

    public function forWorker(Worker $worker): static
    {
        return $this->state(fn () => [
            'tenant_id' => $worker->tenant_id,
            'worker_id' => $worker->id,
        ]);
    }
}
