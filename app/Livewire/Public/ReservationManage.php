<?php

namespace App\Livewire\Public;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\UpdateReservationAction;
use App\Data\Reservations\ReservationBookingData;
use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Http\Requests\Reservations\UpdateReservationRequest;
use App\Models\Reservation;
use App\Support\Portal\ReservationGuestSession;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('WinProx')]
class ReservationManage extends Component
{
    public int $reservationId;

    public string $manageToken = '';

    public string $guestFirstName = '';

    public string $guestLastName = '';

    public string $guestEmail = '';

    public string $startAt = '';

    public string $endAt = '';

    public string $flashMessage = '';

    public function mount(string $token): void
    {
        $reservation = Reservation::withoutGlobalScopes()
            ->with(['unit.location'])
            ->where('manage_token', $token)
            ->first();

        abort_unless($reservation, 404);

        Tenancy::actAs((int) $reservation->tenant_id);
        $this->fillFromReservation($reservation);
        ReservationGuestSession::remember(
            $reservation->guest_first_name,
            $reservation->guest_last_name,
            $reservation->guest_email,
        );
    }

    public function save(UpdateReservationAction $updateReservation): void
    {
        $reservation = $this->reservation();
        $payload = [
            'guest_first_name' => trim($this->guestFirstName),
            'guest_last_name' => trim($this->guestLastName),
            'guest_email' => trim($this->guestEmail),
            'start_at' => $this->startAt,
            'end_at' => $this->endAt,
        ];

        $validator = Validator::make(
            $payload,
            UpdateReservationRequest::ruleSet(),
            StoreReservationRequest::validationMessages(),
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        try {
            $updated = $updateReservation->handle(
                $reservation,
                ReservationBookingData::fromValidated($validator->validated()),
            );
            $this->fillFromReservation($updated);
            ReservationGuestSession::remember(
                $updated->guest_first_name,
                $updated->guest_last_name,
                $updated->guest_email,
            );
            $this->flashMessage = __('reservations.public.updated');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    public function cancel(CancelReservationAction $cancelReservation): void
    {
        try {
            $cancelReservation->handle($this->reservation());
            $this->flashMessage = __('reservations.public.cancelled');
        } catch (ValidationException $e) {
            $this->flashMessage = collect($e->errors())->flatten()->first()
                ?? __('reservations.errors.not_cancellable');
        }
    }

    public function render()
    {
        return view('livewire.public.reservation-manage', [
            'reservation' => $this->reservation(),
        ]);
    }

    private function reservation(): Reservation
    {
        return Reservation::withoutGlobalScopes()
            ->with(['unit.location'])
            ->where('manage_token', $this->manageToken)
            ->findOrFail($this->reservationId);
    }

    private function fillFromReservation(Reservation $reservation): void
    {
        $this->reservationId = (int) $reservation->id;
        $this->manageToken = (string) $reservation->manage_token;
        $this->guestFirstName = $reservation->guest_first_name;
        $this->guestLastName = $reservation->guest_last_name;
        $this->guestEmail = $reservation->guest_email;
        $this->startAt = $reservation->start_at?->format('Y-m-d\TH:i') ?? '';
        $this->endAt = $reservation->end_at?->format('Y-m-d\TH:i') ?? '';
    }
}
