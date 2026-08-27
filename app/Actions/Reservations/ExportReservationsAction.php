<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\Data\Reports\ListExportResult;
use App\Data\Reservations\ExportReservationsFilterData;
use App\Models\Reservation;
use App\Support\Reports\ListExportLimit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ExportReservationsAction
{
    /**
     * @return ListExportResult<Reservation>
     */
    public function handle(int $tenantId, ExportReservationsFilterData $filters): ListExportResult
    {
        $limit = ListExportLimit::MAX;
        $now = now();
        $status = in_array($filters->status, ['upcoming', 'pending', 'confirmed', 'past', 'all'], true)
            ? $filters->status
            : 'upcoming';

        $query = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->with(['unit.location'])
            ->when($filters->locationId, fn ($q) => $q->whereHas(
                'unit',
                fn ($uq) => $uq->where('location_id', $filters->locationId)
            ));

        $this->applyStatusFilter($query, $status, $now);

        $rows = $query
            ->orderBy($status === 'past' ? 'end_at' : 'start_at', $status === 'past' ? 'desc' : 'asc')
            ->limit($limit + 1)
            ->get();

        $truncated = $rows->count() > $limit;
        if ($truncated) {
            $rows = $rows->take($limit)->values();
        }

        return new ListExportResult($rows, $truncated, $limit);
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    private function applyStatusFilter(Builder $query, string $status, CarbonInterface $now): void
    {
        match ($status) {
            'pending' => $query->whereNull('cancelled_at')->whereNull('confirmed_at')->where('expires_at', '>', $now),
            'confirmed' => $query->whereNull('cancelled_at')->whereNotNull('confirmed_at')->where('end_at', '>=', $now),
            'past' => $query->where(function ($q) use ($now) {
                $q->whereNotNull('cancelled_at')
                    ->orWhere('end_at', '<', $now);
            }),
            'all' => null,
            default => $query->whereNull('cancelled_at')->where('end_at', '>=', $now->copy()->subDay()),
        };
    }
}
