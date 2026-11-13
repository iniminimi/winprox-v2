<?php

namespace App\Livewire\Time;

use App\Actions\Time\WorkerRecupBalanceAction;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class OvertimeIndex extends Component
{
    use AuthorizesRequests;
    use ProvidesTimeNavAlarmCount;

    public function boot(): void
    {
        abort_unless(TimeModuleAccess::rosterEnabledForTenantId((int) Tenancy::id()), 403);
    }

    public function mount(): void
    {
        $this->authorize('viewAny', WorkShift::class);
        TimeModuleAccess::assertEnabledForTenantId((int) Tenancy::id());
    }

    public function render(WorkerRecupBalanceAction $overtime)
    {
        $tenant = Tenant::query()->find((int) Tenancy::id());
        $workers = $tenant instanceof Tenant
            ? Worker::query()
                ->where('tenant_id', $tenant->id)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
            : collect();

        $byWorker = $tenant instanceof Tenant
            ? $overtime->monthly($tenant, $workers->pluck('id')->map(fn ($id) => (int) $id)->all())
            : [];

        $rows = [];
        foreach ($workers as $worker) {
            $months = $byWorker[(int) $worker->id] ?? array_fill(1, 12, 0);
            $total = array_sum($months);
            if (! $worker->is_active && $total === 0) {
                continue;
            }
            $rows[] = [
                'name' => $worker->displayName(),
                'months' => $months,
                'total' => $total,
            ];
        }

        $currentMonth = (int) now()->month;
        $visibleMonths = range(1, $currentMonth);
        for ($month = $currentMonth + 1; $month <= 12; $month++) {
            foreach ($rows as $row) {
                if (($row['months'][$month] ?? 0) !== 0) {
                    $visibleMonths[] = $month;
                    break;
                }
            }
        }

        $year = (int) now()->year;
        $monthLabels = [];
        foreach ($visibleMonths as $month) {
            $monthLabels[$month] = Carbon::parse(sprintf('%04d-%02d-01', $year, $month))
                ->locale(app()->getLocale())
                ->translatedFormat('M');
        }

        return view('livewire.time.overtime-index', [
            'rows' => $rows,
            'year' => $year,
            'monthLabels' => $monthLabels,
            'alarmCount' => $this->timeNavAlarmCount(),
        ]);
    }
}
