<?php

declare(strict_types=1);

namespace App\Actions\Time;

use App\Models\WorkVisit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ListWorkVisitsAction
{
    /**
     * @return LengthAwarePaginator<int, array{worker_id: int, date: string, worker: \App\Models\Worker|null, visits: Collection<int, WorkVisit>, has_open: bool, total_minutes: int}>
     */
    public function handle(
        int $tenantId,
        ?string $from = null,
        ?string $to = null,
        ?int $workerId = null,
        ?int $locationId = null,
        ?string $status = null,
        int $perPage = 25,
        int $page = 1,
    ): LengthAwarePaginator {
        $query = WorkVisit::query()
            ->where('tenant_id', $tenantId)
            ->when(filled($from), fn ($query) => $query->where('started_at', '>=', $from.' 00:00:00'))
            ->when(filled($to), fn ($query) => $query->where('started_at', '<=', $to.' 23:59:59'))
            ->when($workerId !== null, fn ($query) => $query->where('worker_id', $workerId))
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->when($status === 'open', fn ($query) => $query->open())
            ->when($status === 'closed', fn ($query) => $query->whereNotNull('ended_at'));

        $groupRows = (clone $query)
            ->selectRaw('worker_id, DATE(started_at) as visit_date, MAX(started_at) as last_started_at')
            ->groupByRaw('worker_id, DATE(started_at)')
            ->orderByDesc('last_started_at')
            ->get();

        $page = max(1, $page);
        $slice = $groupRows->forPage($page, $perPage)->values();

        $visitsByDay = collect();
        if ($slice->isNotEmpty()) {
            $visitsByDay = (clone $query)
                ->with(['worker', 'unit.translations', 'location'])
                ->where(function ($query) use ($slice) {
                    foreach ($slice as $row) {
                        $date = Carbon::parse((string) $row->visit_date)->toDateString();
                        $query->orWhere(function ($inner) use ($row, $date) {
                            $inner->where('worker_id', $row->worker_id)
                                ->whereDate('started_at', $date);
                        });
                    }
                })
                ->orderBy('started_at')
                ->orderBy('id')
                ->get()
                ->groupBy(fn (WorkVisit $visit) => $visit->worker_id.'|'.$visit->started_at?->toDateString());
        }

        $days = $slice->map(function ($row) use ($visitsByDay) {
            $date = Carbon::parse((string) $row->visit_date)->toDateString();
            $visits = $visitsByDay->get($row->worker_id.'|'.$date, collect())->values();

            return [
                'worker_id' => (int) $row->worker_id,
                'date' => $date,
                'worker' => $visits->first()?->worker,
                'visits' => $visits,
                'has_open' => $visits->contains(fn (WorkVisit $visit) => $visit->isOpen()),
                'total_minutes' => (int) $visits->sum(fn (WorkVisit $visit) => $visit->durationMinutes()),
            ];
        });

        return new Paginator($days, $groupRows->count(), $perPage, $page);
    }
}
