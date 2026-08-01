<?php

namespace Database\Factories;

use App\Enums\UnitCheckResult;
use App\Enums\UnitCheckSource;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitCheck;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitCheck> */
class UnitCheckFactory extends Factory
{
    protected $model = UnitCheck::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'location_id' => Location::factory(),
            'worker_id' => null,
            'internal_team_id' => null,
            'result' => UnitCheckResult::Ok,
            'source' => UnitCheckSource::Portal,
            'checked_at' => now(),
            'latitude' => null,
            'longitude' => null,
            'task_id' => null,
            'issue_id' => null,
            'checklist_items' => null,
        ];
    }

    public function notOk(): static
    {
        return $this->state(fn () => [
            'result' => UnitCheckResult::NotOk,
        ]);
    }

    public function withGps(?float $latitude = null, ?float $longitude = null): static
    {
        return $this->state(fn () => [
            'latitude' => $latitude ?? fake()->latitude(),
            'longitude' => $longitude ?? fake()->longitude(),
        ]);
    }

    public function forWorker(Worker $worker): static
    {
        return $this->state(fn () => [
            'tenant_id' => $worker->tenant_id,
            'worker_id' => $worker->id,
            'internal_team_id' => $worker->internal_team_id,
        ]);
    }
}
