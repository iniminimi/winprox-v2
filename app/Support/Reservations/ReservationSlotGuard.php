<?php

namespace App\Support\Reservations;

use App\Models\Reservation;
use App\Models\Unit;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class ReservationSlotGuard
{
    public static function assertUnitReservable(Unit $unit): void
    {
        $unit->loadMissing('category');

        if (! $unit->isReservable()) {
            throw ValidationException::withMessages([
                'unit_id' => [__('reservations.errors.unit_not_reservable')],
            ]);
        }
    }

    public static function assertWindowValid(CarbonInterface $start, CarbonInterface $end): void
    {
        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'end_at' => [__('reservations.errors.end_after_start')],
            ]);
        }

        if ($start->lt(now()->subMinute())) {
            throw ValidationException::withMessages([
                'start_at' => [__('reservations.errors.start_in_past')],
            ]);
        }

        if ($start->diffInMinutes($end) < 15) {
            throw ValidationException::withMessages([
                'end_at' => [__('reservations.errors.min_duration')],
            ]);
        }

        if ($start->diffInHours($end) > 24) {
            throw ValidationException::withMessages([
                'end_at' => [__('reservations.errors.max_duration')],
            ]);
        }
    }

    public static function assertNoOverlap(Unit $unit, CarbonInterface $start, CarbonInterface $end, ?int $ignoreReservationId = null): void
    {
        $exists = Reservation::query()
            ->where('tenant_id', $unit->tenant_id)
            ->where('unit_id', $unit->id)
            ->blocking()
            ->overlapping($start, $end, $ignoreReservationId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_at' => [__('reservations.errors.overlap')],
            ]);
        }
    }

    public static function parseWindow(string $startAt, string $endAt): array
    {
        $start = Carbon::parse($startAt);
        $end = Carbon::parse($endAt);

        return [$start, $end];
    }
}
