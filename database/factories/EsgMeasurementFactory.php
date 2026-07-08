<?php

namespace Database\Factories;

use App\Enums\EsgIndicatorType;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EsgMeasurement> */
class EsgMeasurementFactory extends Factory
{
    protected $model = EsgMeasurement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'location_id' => null,
            'task_id' => Task::factory(),
            'esg_indicator_id' => EsgIndicator::factory()->numeric(),
            'worker_id' => null,
            'value_numeric' => fake()->randomFloat(4, 0, 9999),
            'value_boolean' => null,
            'value_string' => null,
            'value_json' => null,
            'corrects_measurement_id' => null,
            'recorded_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (EsgMeasurement $measurement): void {
            $this->alignRelations($measurement);
            $this->applyIndicatorValue($measurement);
        });
    }

    private function alignRelations(EsgMeasurement $measurement): void
    {
        if ($measurement->task_id !== null) {
            $task = Task::query()->withoutGlobalScopes()->find($measurement->task_id);
            if ($task instanceof Task) {
                $measurement->tenant_id ??= $task->tenant_id;

                $issue = Issue::query()->withoutGlobalScopes()->find($task->issue_id);
                if ($issue instanceof Issue) {
                    $measurement->unit_id ??= $issue->unit_id;
                    $measurement->location_id ??= $issue->location_id;
                }
            }
        }

        if ($measurement->unit_id !== null) {
            $unit = Unit::query()->withoutGlobalScopes()->find($measurement->unit_id);
            if ($unit instanceof Unit) {
                $measurement->tenant_id ??= $unit->tenant_id;
                $measurement->location_id ??= $unit->location_id;
            }
        }

        if ($measurement->esg_indicator_id !== null) {
            $indicator = EsgIndicator::query()->withoutGlobalScopes()->find($measurement->esg_indicator_id);
            if ($indicator instanceof EsgIndicator) {
                $measurement->tenant_id ??= $indicator->tenant_id;
            }
        }
    }

    private function applyIndicatorValue(EsgMeasurement $measurement): void
    {
        $indicator = $measurement->esg_indicator_id !== null
            ? EsgIndicator::query()->withoutGlobalScopes()->find($measurement->esg_indicator_id)
            : null;

        if (! $indicator instanceof EsgIndicator) {
            return;
        }

        $measurement->value_numeric = null;
        $measurement->value_boolean = null;
        $measurement->value_string = null;
        $measurement->value_json = null;

        match ($indicator->type) {
            EsgIndicatorType::Numeric => $measurement->value_numeric = fake()->randomFloat(4, 0, 9999),
            EsgIndicatorType::Boolean => $measurement->value_boolean = fake()->boolean(),
            EsgIndicatorType::String => $measurement->value_string = fake()->sentence(),
            EsgIndicatorType::Json => $measurement->value_json = ['reading' => fake()->randomFloat(2, 0, 100)],
        };
    }
}
