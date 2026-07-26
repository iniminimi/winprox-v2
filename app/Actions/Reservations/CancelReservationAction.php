<?php

namespace App\Actions\Reservations;

use App\Events\Reservations\ReservationCancelled;
use App\Models\Reservation;
use Illuminate\Validation\ValidationException;

class CancelReservationAction
{
    public function handle(Reservation $reservation, ?int $actorUserId = null): Reservation
    {
        if (! $reservation->isCancellable()) {
            throw ValidationException::withMessages([
                'reservation' => [__('reservations.errors.not_cancellable')],
            ]);
        }

        $reservation->forceFill([
            'cancelled_at' => now(),
            'expires_at' => null,
        ])->save();

        event(new ReservationCancelled($reservation, $actorUserId));

        return $reservation->refresh();
    }
}
