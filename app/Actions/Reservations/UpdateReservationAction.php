<?php

namespace App\Actions\Reservations;

use App\Data\Reservations\ReservationBookingData;
use App\Events\Reservations\ReservationUpdated;
use App\Models\Reservation;
use App\Support\Reservations\ReservationSlotGuard;
use Illuminate\Validation\ValidationException;

class UpdateReservationAction
{
    public function handle(Reservation $reservation, ReservationBookingData $data, ?int $actorUserId = null): Reservation
    {
        if (! $reservation->isEditable()) {
            throw ValidationException::withMessages([
                'reservation' => [__('reservations.errors.not_editable')],
            ]);
        }

        $unit = $reservation->unit()->with('category')->firstOrFail();
        ReservationSlotGuard::assertUnitReservable($unit);
        [$start, $end] = ReservationSlotGuard::parseWindow($data->startAt, $data->endAt);
        ReservationSlotGuard::assertWindowValid($start, $end);
        ReservationSlotGuard::assertNoOverlap($unit, $start, $end, (int) $reservation->id);

        $reservation->forceFill([
            'guest_first_name' => $data->guestFirstName,
            'guest_last_name' => $data->guestLastName,
            'guest_email' => $data->guestEmail,
            'start_at' => $start,
            'end_at' => $end,
        ])->save();

        event(new ReservationUpdated($reservation, $actorUserId));

        return $reservation->refresh();
    }
}
