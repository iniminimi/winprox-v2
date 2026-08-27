<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\Reservation;
use Illuminate\Support\Collection;

final class ReservationExportTable
{
    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
            __('reports.columns.id'),
            __('reports.columns.start_at'),
            __('reports.columns.end_at'),
            __('reports.columns.status'),
            __('reports.columns.location'),
            __('reports.columns.unit'),
            __('reports.columns.guest'),
            __('reports.columns.email'),
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return Collection<int, list<string>>
     */
    public static function rows(Collection $reservations): Collection
    {
        return $reservations->map(function (Reservation $reservation): array {
            return [
                (string) $reservation->id,
                $reservation->start_at?->format('Y-m-d H:i') ?? '',
                $reservation->end_at?->format('Y-m-d H:i') ?? '',
                __('reservations.lifecycle.'.$reservation->lifecycle()->value),
                (string) ($reservation->unit?->location?->name ?? ''),
                (string) ($reservation->unit?->name ?? ''),
                $reservation->guestFullName(),
                (string) $reservation->guest_email,
            ];
        });
    }
}
