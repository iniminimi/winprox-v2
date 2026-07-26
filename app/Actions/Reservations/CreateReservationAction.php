<?php

namespace App\Actions\Reservations;

use App\Data\Reservations\ReservationBookingData;
use App\Events\Reservations\ReservationCreated;
use App\Mail\ReservationConfirmMail;
use App\Models\Reservation;
use App\Models\Unit;
use App\Support\Reservations\ReservationSlotGuard;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateReservationAction
{
    public function handle(Unit $unit, ReservationBookingData $data): Reservation
    {
        ReservationSlotGuard::assertUnitReservable($unit);
        [$start, $end] = ReservationSlotGuard::parseWindow($data->startAt, $data->endAt);
        ReservationSlotGuard::assertWindowValid($start, $end);
        ReservationSlotGuard::assertNoOverlap($unit, $start, $end);

        $now = now();
        $autoConfirm = $data->autoConfirm;

        $reservation = Reservation::query()->create([
            'tenant_id' => $unit->tenant_id,
            'unit_id' => $unit->id,
            'worker_id' => $data->workerId,
            'created_by_user_id' => $data->createdByUserId,
            'guest_first_name' => $data->guestFirstName,
            'guest_last_name' => $data->guestLastName,
            'guest_email' => $data->guestEmail,
            'start_at' => $start,
            'end_at' => $end,
            'expires_at' => $autoConfirm ? null : $now->copy()->addMinutes(Reservation::HOLD_MINUTES),
            'confirmed_at' => $autoConfirm ? $now : null,
            'cancelled_at' => null,
            'confirm_token' => Str::lower(Str::random(48)),
            'manage_token' => Str::lower(Str::random(48)),
        ]);

        event(new ReservationCreated($reservation, $data->createdByUserId));

        // Synchroon: 30-min bevestigingshold mag niet op een trage/falende queue wachten.
        Mail::to($reservation->guest_email)->send(
            new ReservationConfirmMail($reservation, alreadyConfirmed: $autoConfirm)
        );

        return $reservation;
    }
}
