<?php

declare(strict_types=1);

namespace App\Actions\Dev;

use App\Enums\BreakType;
use App\Enums\ClockSource;
use App\Enums\WorkShiftStatus;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkBreak;
use App\Models\WorkShift;
use App\Support\Portal\WorkerIcon;
use App\Support\Tenancy;
use Illuminate\Support\Collection;

final class SeedTenantPresenceAction
{
    /**
     * @return array{open_shifts: int, on_break: int, present: int, workers_total: int, alarms_seeded: int}
     */
    public function handle(Tenant $tenant, int $openTarget, int $onBreakTarget, int $alarmsTarget = 0): array
    {
        $openTarget = max(0, $openTarget);
        $onBreakTarget = max(0, min($onBreakTarget, $openTarget));

        if (! $tenant->hasTimeModule()) {
            $tenant->update(['has_time_module' => true]);
            $tenant->refresh();
        }

        Tenancy::actAs($tenant->id);

        try {
            $clockPoints = $this->ensureClockPoints($tenant);
            $this->ensureWorkerCount($tenant, $openTarget);

            $this->ensureOpenShiftCount($tenant, $clockPoints, $openTarget);
            $this->ensureOnBreakCount($tenant, $onBreakTarget);
            $alarmsSeeded = $this->ensureDemoAlarms($tenant, $alarmsTarget);

            $openShifts = WorkShift::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', WorkShiftStatus::Open)
                ->with('openBreak')
                ->get();

            $onBreak = $openShifts->filter(fn (WorkShift $shift) => $shift->openBreak !== null)->count();
            $present = $openShifts->count() - $onBreak;

            return [
                'open_shifts' => $openShifts->count(),
                'on_break' => $onBreak,
                'present' => $present,
                'workers_total' => Worker::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->count(),
                'alarms_seeded' => $alarmsSeeded,
            ];
        } finally {
            Tenancy::forget();
        }
    }

    /** @return Collection<int, ClockPoint> */
    private function ensureClockPoints(Tenant $tenant): Collection
    {
        $clockPoints = ClockPoint::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($clockPoints->isNotEmpty()) {
            return $clockPoints;
        }

        app(SeedTenantDemoDataAction::class)->handle($tenant, [
            'clock_points' => 10,
            'esg' => false,
            'time' => false,
        ]);

        return ClockPoint::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    private function ensureWorkerCount(Tenant $tenant, int $minimum): void
    {
        $current = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->count();

        if ($current >= $minimum) {
            return;
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

        $teamIds = $teams->pluck('id')->all();
        $iconSlugs = WorkerIcon::SLUGS;
        $toCreate = $minimum - $current;

        for ($i = 0; $i < $toCreate; $i++) {
            $index = $current + $i;
            Worker::factory()->create([
                'tenant_id' => $tenant->id,
                'internal_team_id' => $teamIds[$index % count($teamIds)],
                'field_icon_slug' => $iconSlugs[$index % count($iconSlugs)],
            ]);
        }
    }

    /** @param  Collection<int, ClockPoint>  $clockPoints */
    private function ensureOpenShiftCount(Tenant $tenant, Collection $clockPoints, int $target): void
    {
        $currentOpen = WorkShift::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', WorkShiftStatus::Open)
            ->count();

        if ($currentOpen >= $target) {
            return;
        }

        $needed = $target - $currentOpen;
        $clockedInWorkerIds = WorkShift::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', WorkShiftStatus::Open)
            ->pluck('worker_id')
            ->all();

        $workers = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->when($clockedInWorkerIds !== [], fn ($q) => $q->whereNotIn('id', $clockedInWorkerIds))
            ->orderBy('id')
            ->limit($needed)
            ->get();

        foreach ($workers as $worker) {
            WorkShift::factory()->create([
                'tenant_id' => $tenant->id,
                'worker_id' => $worker->id,
                'internal_team_id' => $worker->internal_team_id,
                'clock_in_clock_point_id' => $clockPoints->random()->id,
                'status' => WorkShiftStatus::Open,
                'clock_in_at' => now()->subHours(random_int(1, 8)),
                'clock_in_source' => ClockSource::ClockPointQr,
            ]);
        }
    }

    private function ensureOnBreakCount(Tenant $tenant, int $target): void
    {
        $openShifts = WorkShift::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', WorkShiftStatus::Open)
            ->with('openBreak')
            ->get();

        $currentOnBreak = $openShifts->filter(fn (WorkShift $shift) => $shift->openBreak !== null)->count();

        if ($currentOnBreak >= $target) {
            return;
        }

        $needed = $target - $currentOnBreak;

        $candidates = $openShifts
            ->filter(fn (WorkShift $shift) => $shift->openBreak === null)
            ->take($needed);

        foreach ($candidates as $shift) {
            WorkBreak::create([
                'tenant_id' => $tenant->id,
                'work_shift_id' => $shift->id,
                'started_at' => now()->subMinutes(random_int(5, 45)),
                'ended_at' => null,
                'break_type' => BreakType::Break,
            ]);
        }
    }

    /**
     * Zet clock_in_at terug op een subset open shifts zodat aandachtsregels alarmen opleveren.
     */
    private function ensureDemoAlarms(Tenant $tenant, int $target): int
    {
        if ($target <= 0) {
            return 0;
        }

        $shifts = WorkShift::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', WorkShiftStatus::Open)
            ->inRandomOrder()
            ->limit($target)
            ->get();

        $patterns = [17, 11, 7];
        $updatedIds = [];

        foreach ($shifts as $index => $shift) {
            $hours = $patterns[$index % count($patterns)];
            $shift->update(['clock_in_at' => now()->subHours($hours)]);
            $updatedIds[] = $shift->id;
        }

        WorkShift::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', WorkShiftStatus::Open)
            ->when($updatedIds !== [], fn ($q) => $q->whereNotIn('id', $updatedIds))
            ->update(['clock_in_at' => now()->subHours(2)]);

        return $shifts->count();
    }
}
