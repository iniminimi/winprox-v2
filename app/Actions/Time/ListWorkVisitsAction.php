<?php

declare(strict_types=1);

namespace App\Actions\Time;

use App\Models\WorkVisit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListWorkVisitsAction
{
    /**
     * @return LengthAwarePaginator<int, WorkVisit>
     */
    public function handle(
        int $tenantId,
        ?string $from = null,
        ?string $to = null,
        ?int $workerId = null,
        ?int $locationId = null,
        ?string $status = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        return WorkVisit::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'worker',
                'unit.translations',
                'location',
            ])
            ->when(filled($from), fn ($query) => $query->where('started_at', '>=', $from.' 00:00:00'))
            ->when(filled($to), fn ($query) => $query->where('started_at', '<=', $to.' 23:59:59'))
            ->when($workerId !== null, fn ($query) => $query->where('worker_id', $workerId))
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->when($status === 'open', fn ($query) => $query->open())
            ->when($status === 'closed', fn ($query) => $query->whereNotNull('ended_at'))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
