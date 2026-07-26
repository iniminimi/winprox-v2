<?php

namespace App\Livewire\Pages;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Actions\Reservations\UpdateReservationAction;
use App\Data\Reservations\ReservationBookingData;
use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Http\Requests\Reservations\UpdateReservationRequest;
use App\Models\Reservation;
use App\Models\Unit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ReservationsIndex extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $unitId = null;

    public string $guestFirstName = '';

    public string $guestLastName = '';

    public string $guestEmail = '';

    public string $startAt = '';

    public string $endAt = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Reservation::class);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Reservation::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $reservationId): void
    {
        $reservation = Reservation::query()->with('unit')->findOrFail($reservationId);
        $this->authorize('update', $reservation);
        $this->editingId = (int) $reservation->id;
        $this->unitId = (int) $reservation->unit_id;
        $this->guestFirstName = $reservation->guest_first_name;
        $this->guestLastName = $reservation->guest_last_name;
        $this->guestEmail = $reservation->guest_email;
        $this->startAt = $reservation->start_at?->format('Y-m-d\TH:i') ?? '';
        $this->endAt = $reservation->end_at?->format('Y-m-d\TH:i') ?? '';
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
        $this->resetErrorBag();
    }

    public function save(CreateReservationAction $createReservation, UpdateReservationAction $updateReservation): void
    {
        $rules = $this->editingId === null
            ? array_merge(StoreReservationRequest::ruleSet(), [
                'unit_id' => ['required', 'integer', 'exists:units,id'],
            ])
            : UpdateReservationRequest::ruleSet();

        $payload = [
            'unit_id' => $this->unitId,
            'guest_first_name' => trim($this->guestFirstName),
            'guest_last_name' => trim($this->guestLastName),
            'guest_email' => trim($this->guestEmail),
            'start_at' => $this->startAt,
            'end_at' => $this->endAt,
        ];

        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $validated = $validator->validated();

        try {
            if ($this->editingId === null) {
                $this->authorize('create', Reservation::class);
                $unit = Unit::query()->with('category')->findOrFail((int) $validated['unit_id']);
                $createReservation->handle(
                    $unit,
                    ReservationBookingData::fromValidated(
                        $validated,
                        autoConfirm: true,
                        createdByUserId: (int) auth()->id(),
                    ),
                );
            } else {
                $reservation = Reservation::query()->findOrFail($this->editingId);
                $this->authorize('update', $reservation);
                $updateReservation->handle(
                    $reservation,
                    ReservationBookingData::fromValidated($validated),
                    (int) auth()->id(),
                );
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $this->closeForm();
        $this->resetPage();
    }

    public function cancelReservation(int $reservationId, CancelReservationAction $cancelReservation): void
    {
        $reservation = Reservation::query()->findOrFail($reservationId);
        $this->authorize('delete', $reservation);
        $cancelReservation->handle($reservation, (int) auth()->id());
    }

    public function render()
    {
        $reservations = Reservation::query()
            ->with(['unit.location'])
            ->whereNull('cancelled_at')
            ->where('end_at', '>=', now()->subDay())
            ->orderBy('start_at')
            ->paginate(25);

        $units = Unit::query()
            ->with(['location', 'category'])
            ->where('is_active', true)
            ->where('allow_reservations', true)
            ->whereHas('category', fn ($q) => $q->where('is_reservable', true))
            ->orderBy('name')
            ->get();

        return view('livewire.pages.reservations-index', [
            'reservations' => $reservations,
            'units' => $units,
        ]);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->unitId = null;
        $this->guestFirstName = '';
        $this->guestLastName = '';
        $this->guestEmail = '';
        $this->startAt = '';
        $this->endAt = '';
    }
}
