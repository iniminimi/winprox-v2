<?php

declare(strict_types=1);

namespace App\Actions\Dev;

use App\Actions\Esg\CreateEsgIndicatorAction;
use App\Actions\Time\CreateClockPointAction;
use App\Enums\BreakType;
use App\Enums\ClockSource;
use App\Enums\EsgIndicatorType;
use App\Enums\WorkShiftStatus;
use App\Models\ClockPoint;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkBreak;
use App\Models\WorkShift;
use App\Support\Portal\WorkerIcon;
use App\Support\Tenancy;
use Illuminate\Support\Collection;

final class SeedTenantDemoDataAction
{
    public function __construct(
        private CreateClockPointAction $createClockPoint,
        private CreateEsgIndicatorAction $createEsgIndicator,
    ) {}

    /**
     * @param  array{clock_points?: int, esg?: bool, time?: bool}  $options
     * @return array{
     *     clock_points_created: int,
     *     clock_points_total: int,
     *     esg_indicators: int,
     *     esg_measurements: int,
     *     work_shifts: int,
     *     workers_total: int
     * }
     */
    public function handle(Tenant $tenant, array $options = []): array
    {
        $clockPointTarget = max(0, (int) ($options['clock_points'] ?? 10));
        $seedEsg = (bool) ($options['esg'] ?? true);
        $seedTime = (bool) ($options['time'] ?? true);

        $updates = [];
        if (! $tenant->hasEsgModule()) {
            $updates['has_esg_module'] = true;
        }
        if (! $tenant->hasTimeModule()) {
            $updates['has_time_module'] = true;
        }
        if ($updates !== []) {
            $tenant->update($updates);
            $tenant->refresh();
        }

        Tenancy::actAs($tenant->id);

        try {
            $actorUserId = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('role', User::ROLE_ADMIN)
                ->value('id');

            $locations = $this->ensureLocations($tenant);
            $clockPointsCreated = $this->seedClockPoints($tenant, $locations, $clockPointTarget, $actorUserId);

            $esgIndicators = 0;
            $esgMeasurements = 0;
            if ($seedEsg) {
                [$esgIndicators, $esgMeasurements] = $this->seedEsg($tenant, $locations, $actorUserId);
            }

            $workShifts = 0;
            if ($seedTime) {
                $workShifts = $this->seedTime($tenant, $locations);
            }

            return [
                'clock_points_created' => $clockPointsCreated,
                'clock_points_total' => ClockPoint::query()->where('tenant_id', $tenant->id)->count(),
                'esg_indicators' => $esgIndicators,
                'esg_measurements' => $esgMeasurements,
                'work_shifts' => $workShifts,
                'workers_total' => Worker::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            ];
        } finally {
            Tenancy::forget();
        }
    }

    /** @return Collection<int, Location> */
    private function ensureLocations(Tenant $tenant): Collection
    {
        $locations = Location::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get();

        if ($locations->isNotEmpty()) {
            return $locations;
        }

        return collect([
            Location::create([
                'tenant_id' => $tenant->id,
                'name' => 'Demo hoofdgebouw',
                'address' => 'Seedstraat 1',
            ]),
            Location::create([
                'tenant_id' => $tenant->id,
                'name' => 'Demo magazijn',
                'address' => 'Seedstraat 2',
            ]),
        ]);
    }

    /** @param  Collection<int, Location>  $locations */
    private function seedClockPoints(Tenant $tenant, Collection $locations, int $target, ?int $actorUserId): int
    {
        if ($target === 0) {
            return 0;
        }

        $existing = ClockPoint::query()->where('tenant_id', $tenant->id)->count();
        $toCreate = max(0, $target - $existing);

        if ($toCreate === 0) {
            return 0;
        }

        $created = 0;
        $locationIds = $locations->pluck('id')->all();
        $startSort = (int) ClockPoint::query()->where('tenant_id', $tenant->id)->max('sort_order');

        for ($i = 1; $i <= $toCreate; $i++) {
            $index = $existing + $i;
            $this->createClockPoint->handle($tenant, [
                'name' => sprintf('Clock Point %02d', $index),
                'location_id' => $locationIds[($index - 1) % count($locationIds)],
                'is_active' => true,
                'sort_order' => $startSort + $i,
            ], $actorUserId);
            $created++;
        }

        return $created;
    }

    /**
     * @param  Collection<int, Location>  $locations
     * @return array{0: int, 1: int}
     */
    private function seedEsg(Tenant $tenant, Collection $locations, ?int $actorUserId): array
    {
        $units = $this->ensureUnits($tenant, $locations);
        $definitions = [
            ['name' => 'Elektriciteit kWh', 'type' => EsgIndicatorType::Numeric, 'unit_of_measure' => 'kWh', 'thresholds' => ['min' => 0, 'max' => 5000]],
            ['name' => 'Gas m³', 'type' => EsgIndicatorType::Numeric, 'unit_of_measure' => 'm³', 'thresholds' => ['min' => 0, 'max' => 500]],
            ['name' => 'CO₂ kg', 'type' => EsgIndicatorType::Numeric, 'unit_of_measure' => 'kg', 'thresholds' => null],
            ['name' => 'Veiligheidsinspectie OK', 'type' => EsgIndicatorType::Boolean, 'unit_of_measure' => null, 'thresholds' => null],
            ['name' => 'Opmerking inspectie', 'type' => EsgIndicatorType::String, 'unit_of_measure' => null, 'thresholds' => null],
            ['name' => 'Sensor snapshot', 'type' => EsgIndicatorType::Json, 'unit_of_measure' => null, 'thresholds' => null],
            ['name' => 'Afvalstroom', 'type' => EsgIndicatorType::Choice, 'unit_of_measure' => null, 'thresholds' => null, 'options' => ['Restafval', 'PMD', 'Papier']],
            ['name' => 'Energiebronnen', 'type' => EsgIndicatorType::MultiChoice, 'unit_of_measure' => null, 'thresholds' => null, 'options' => ['Zon', 'Net', 'Generator']],
        ];

        $indicatorCount = 0;
        $measurementCount = 0;

        foreach ($definitions as $definition) {
            $name = $definition['name'];

            if (EsgIndicator::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', $name)
                ->exists()) {
                continue;
            }

            $indicator = $this->createEsgIndicator->handle($tenant->id, $definition, $actorUserId);
            $indicatorCount++;

            for ($n = 0; $n < 12; $n++) {
                $unit = $units->random();
                $issue = Issue::factory()->create([
                    'tenant_id' => $tenant->id,
                    'location_id' => $unit->location_id,
                    'unit_id' => $unit->id,
                    'esg_indicator_id' => $indicator->id,
                    'is_recurring' => true,
                    'description' => 'Demo ESG — '.$indicator->name,
                ]);
                $task = Task::factory()->create([
                    'tenant_id' => $tenant->id,
                    'issue_id' => $issue->id,
                ]);

                $this->createDemoMeasurement($tenant, $indicator, $task, $unit);
                $measurementCount++;
            }
        }

        return [$indicatorCount, $measurementCount];
    }

    private function createDemoMeasurement(Tenant $tenant, EsgIndicator $indicator, Task $task, Unit $unit): void
    {
        $values = match ($indicator->type) {
            EsgIndicatorType::Numeric => ['value_numeric' => fake()->randomFloat(4, 0, 9999)],
            EsgIndicatorType::Boolean => ['value_boolean' => fake()->boolean()],
            EsgIndicatorType::String => ['value_string' => fake()->sentence()],
            EsgIndicatorType::Json => ['value_json' => ['reading' => fake()->numberBetween(0, 100)]],
            EsgIndicatorType::Choice => ['value_string' => fake()->randomElement($indicator->options ?? ['Onbekend'])],
            EsgIndicatorType::MultiChoice => ['value_json' => [fake()->randomElement($indicator->options ?? ['Onbekend'])]],
        };

        EsgMeasurement::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'task_id' => $task->id,
            'esg_indicator_id' => $indicator->id,
            'unit_id' => $unit->id,
            'location_id' => $unit->location_id,
            'recorded_at' => now()->subDays(random_int(0, 120))->subHours(random_int(0, 23)),
            'created_at' => now(),
        ], $values));
    }

    /** @param  Collection<int, Location>  $locations */
    private function ensureUnits(Tenant $tenant, Collection $locations): Collection
    {
        $units = Unit::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get();

        if ($units->count() >= 5) {
            return $units;
        }

        $needed = 5 - $units->count();
        $locationIds = $locations->pluck('id')->all();

        for ($i = 1; $i <= $needed; $i++) {
            $units->push(Unit::create([
                'tenant_id' => $tenant->id,
                'location_id' => $locationIds[($i - 1) % count($locationIds)],
                'name' => 'Demo unit '.$i,
            ]));
        }

        return $units;
    }

    /** @param  Collection<int, Location>  $locations */
    private function seedTime(Tenant $tenant, Collection $locations): int
    {
        $workers = $this->ensureWorkers($tenant)->take(25);
        $clockPoints = ClockPoint::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($clockPoints->isEmpty()) {
            $this->seedClockPoints($tenant, $locations, 10, null);
            $clockPoints = ClockPoint::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        $created = 0;
        $maxCreated = 45;
        $workersWithoutOpenShift = $workers->filter(
            fn (Worker $worker) => ! WorkShift::query()
                ->where('tenant_id', $tenant->id)
                ->where('worker_id', $worker->id)
                ->where('status', WorkShiftStatus::Open)
                ->exists()
        )->values();

        foreach ($workersWithoutOpenShift->take(6) as $worker) {
            if ($created >= $maxCreated) {
                break;
            }
            WorkShift::factory()->create([
                'tenant_id' => $tenant->id,
                'worker_id' => $worker->id,
                'internal_team_id' => $worker->internal_team_id,
                'clock_in_clock_point_id' => $clockPoints->random()->id,
                'status' => WorkShiftStatus::Open,
                'clock_in_at' => now()->subHours(random_int(1, 6)),
                'clock_in_source' => ClockSource::ClockPointQr,
            ]);
            $created++;
        }

        foreach ($workersWithoutOpenShift->slice(6, 3) as $worker) {
            if ($created >= $maxCreated) {
                break;
            }
            $shift = WorkShift::factory()->create([
                'tenant_id' => $tenant->id,
                'worker_id' => $worker->id,
                'internal_team_id' => $worker->internal_team_id,
                'clock_in_clock_point_id' => $clockPoints->random()->id,
                'status' => WorkShiftStatus::Open,
                'clock_in_at' => now()->subHours(random_int(2, 5)),
                'clock_in_source' => ClockSource::ClockPointQr,
            ]);
            WorkBreak::create([
                'tenant_id' => $tenant->id,
                'work_shift_id' => $shift->id,
                'started_at' => now()->subMinutes(20),
                'ended_at' => null,
                'break_type' => BreakType::Break,
            ]);
            $created++;
        }

        foreach ($workers as $worker) {
            if ($created >= $maxCreated) {
                break;
            }

            $closedCount = WorkShift::query()
                ->where('tenant_id', $tenant->id)
                ->where('worker_id', $worker->id)
                ->whereIn('status', [WorkShiftStatus::Closed, WorkShiftStatus::ForceClosed])
                ->count();

            if ($closedCount >= 2) {
                continue;
            }

            for ($i = $closedCount; $i < 2; $i++) {
                if ($created >= $maxCreated) {
                    break;
                }
                $clockIn = now()->subDays(random_int(1, 21))->setTime(random_int(6, 9), random_int(0, 59));
                $clockOut = (clone $clockIn)->addHours(random_int(6, 9));
                WorkShift::factory()->create([
                    'tenant_id' => $tenant->id,
                    'worker_id' => $worker->id,
                    'internal_team_id' => $worker->internal_team_id,
                    'clock_in_clock_point_id' => $clockPoints->random()->id,
                    'clock_out_clock_point_id' => $clockPoints->random()->id,
                    'status' => WorkShiftStatus::Closed,
                    'clock_in_at' => $clockIn,
                    'clock_out_at' => $clockOut,
                    'clock_in_source' => ClockSource::ClockPointQr,
                    'clock_out_source' => ClockSource::ClockPointQr,
                    'total_break_minutes' => random_int(0, 45),
                ]);
                $created++;
            }
        }

        return $created;
    }

    /** @return Collection<int, Worker> */
    private function ensureWorkers(Tenant $tenant): Collection
    {
        $workers = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($workers->count() >= 15) {
            return $workers;
        }

        $teams = InternalTeam::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get();

        if ($teams->isEmpty()) {
            $teams = collect([
                InternalTeam::create(['tenant_id' => $tenant->id, 'name' => 'Techniek']),
                InternalTeam::create(['tenant_id' => $tenant->id, 'name' => 'Schoonmaak']),
                InternalTeam::create(['tenant_id' => $tenant->id, 'name' => 'Logistiek']),
            ]);
        }

        $needed = 15 - $workers->count();
        $teamIds = $teams->pluck('id')->all();
        $iconSlugs = WorkerIcon::SLUGS;

        for ($i = 1; $i <= $needed; $i++) {
            $teamId = $teamIds[($workers->count() + $i - 1) % count($teamIds)];
            $workers->push(Worker::factory()->create([
                'tenant_id' => $tenant->id,
                'internal_team_id' => $teamId,
                'field_icon_slug' => $iconSlugs[($workers->count() + $i - 1) % count($iconSlugs)],
            ]));
        }

        return $workers;
    }
}
