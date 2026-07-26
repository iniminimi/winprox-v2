<?php

namespace App\Livewire\Public;

use App\Actions\Reservations\ConfirmReservationAction;
use App\Models\Reservation;
use App\Support\Portal\ReservationGuestSession;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('WinProx')]
class ReservationConfirm extends Component
{
    public ?int $reservationId = null;

    public string $status = 'pending';

    public string $message = '';

    public function mount(string $token, ConfirmReservationAction $confirmReservation): void
    {
        $reservation = Reservation::withoutGlobalScopes()
            ->with(['unit.location', 'tenant'])
            ->where('confirm_token', $token)
            ->first();

        if ($reservation === null) {
            abort(404);
        }

        Tenancy::actAs((int) $reservation->tenant_id);
        $this->reservationId = (int) $reservation->id;

        try {
            $confirmed = $confirmReservation->handle($reservation);
            ReservationGuestSession::remember(
                $confirmed->guest_first_name,
                $confirmed->guest_last_name,
                $confirmed->guest_email,
            );
            $this->status = 'ok';
            $this->message = __('reservations.public.confirm_ok');
        } catch (ValidationException $e) {
            $this->status = 'error';
            $this->message = collect($e->errors())->flatten()->first()
                ?? __('reservations.public.confirm_failed');
        }
    }

    public function render()
    {
        $reservation = $this->reservationId
            ? Reservation::withoutGlobalScopes()->with(['unit.location'])->find($this->reservationId)
            : null;

        return view('livewire.public.reservation-confirm', [
            'reservation' => $reservation,
        ]);
    }
}
