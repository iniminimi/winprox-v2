<?php

namespace App\Actions\Reservations;

use App\Mail\ReservationConfirmMail;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ResendReservationConfirmMailAction
{
    public function handle(Reservation $reservation): Reservation
    {
        if ($reservation->cancelled_at !== null) {
            throw ValidationException::withMessages([
                'reservation' => [__('reservations.errors.already_cancelled')],
            ]);
        }

        if ($reservation->confirmed_at !== null) {
            Mail::to($reservation->guest_email)->send(
                new ReservationConfirmMail($reservation, alreadyConfirmed: true)
            );

            return $reservation;
        }

        if ($reservation->expires_at !== null && $reservation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'reservation' => [__('reservations.errors.hold_expired')],
            ]);
        }

        Mail::to($reservation->guest_email)->send(
            new ReservationConfirmMail($reservation, alreadyConfirmed: false)
        );

        return $reservation;
    }
}
