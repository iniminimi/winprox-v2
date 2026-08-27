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
        $status = $this->normalizedStatus($filters->status);

        $query = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->with(['unit.location']);

        $this->applyToQuery($query, $filters);

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
    public function applyToQuery(Builder $query, ExportReservationsFilterData $filters): void
    {
        $now = now();
        $status = $this->normalizedStatus($filters->status);

        $query->when($filters->locationId, fn (Builder $q) => $q->whereHas(
            'unit',
            fn ($uq) => $uq->where('location_id', $filters->locationId)
        ));

        $this->applyStatusFilter($query, $status, $now);
        $this->applySearchFilter($query, $filters->search);
    }

    private function normalizedStatus(string $status): string
    {
        return in_array($status, ['upcoming', 'pending', 'confirmed', 'past', 'all'], true)
            ? $status
            : 'upcoming';
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

    /**
     * @param  Builder<Reservation>  $query
     */
    private function applySearchFilter(Builder $query, string $search): void
    {
        $term = trim($search);
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $query->where(function (Builder $builder) use ($like, $term): void {
            $builder->where('guest_first_name', 'like', $like)
                ->orWhere('guest_last_name', 'like', $like)
                ->orWhere('guest_email', 'like', $like)
                ->orWhereHas('unit', function ($unit) use ($like): void {
                    $unit->where('name', 'like', $like)
                        ->orWhereHas('location', function ($location) use ($like): void {
                            $location->where('name', 'like', $like)
                                ->orWhere('address', 'like', $like);
                        });
                });

            $parts = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($parts) >= 2) {
                $builder->orWhere(function (Builder $name) use ($parts): void {
                    $name->where('guest_first_name', 'like', '%'.$parts[0].'%')
                        ->where('guest_last_name', 'like', '%'.$parts[array_key_last($parts)].'%');
                });
            }
        });
    }
}
