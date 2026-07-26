<?php

namespace App\Actions\Reservations;

use App\Events\Reservations\ReservationConfirmed;
use App\Mail\ReservationManageMail;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ConfirmReservationAction
{
    public function handle(Reservation $reservation, ?int $actorUserId = null): Reservation
    {
        if ($reservation->cancelled_at !== null) {
            throw ValidationException::withMessages([
                'reservation' => [__('reservations.errors.already_cancelled')],
            ]);
        }

        if ($reservation->confirmed_at !== null) {
            return $reservation;
        }

        if ($reservation->expires_at !== null && $reservation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'reservation' => [__('reservations.errors.hold_expired')],
            ]);
        }

        $reservation->forceFill([
            'confirmed_at' => now(),
            'expires_at' => null,
        ])->save();

        event(new ReservationConfirmed($reservation, $actorUserId));

        Mail::to($reservation->guest_email)->queue(new ReservationManageMail($reservation));

        return $reservation->refresh();
    }
}
