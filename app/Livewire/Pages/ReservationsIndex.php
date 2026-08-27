<?php

namespace App\Livewire\Pages;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Actions\Reservations\ExportReservationsAction;
use App\Actions\Reservations\ResendReservationConfirmMailAction;
use App\Actions\Reservations\UpdateReservationAction;
use App\Data\Reservations\ExportReservationsFilterData;
use App\Data\Reservations\ReservationBookingData;
use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Http\Requests\Reservations\UpdateReservationRequest;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\Unit;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ReservationsIndex extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = 'upcoming';

    #[Url(as: 'location')]
    public string $locationFilter = '';

    #[Url(as: 'q')]
    public string $search = '';

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

        if (! in_array($this->statusFilter, ['upcoming', 'pending', 'confirmed', 'past', 'all'], true)) {
            $this->statusFilter = 'upcoming';
        }
    }

    public function applyFilters(): void
    {
        $this->redirect(route('reservations.index', array_filter([
            'status' => $this->statusFilter !== 'upcoming' ? $this->statusFilter : null,
            'location' => $this->locationFilter !== '' ? $this->locationFilter : null,
            'q' => trim($this->search) !== '' ? trim($this->search) : null,
        ], fn ($value) => $value !== null && $value !== '')), navigate: true);
    }

    public function resetFilters(): void
    {
        $this->redirect(route('reservations.index'), navigate: true);
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

        $validator = Validator::make($payload, $rules, StoreReservationRequest::validationMessages());

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

    public function resendConfirmMail(int $reservationId, ResendReservationConfirmMailAction $resendConfirmMail): void
    {
        $reservation = Reservation::query()->findOrFail($reservationId);
        $this->authorize('update', $reservation);
        $resendConfirmMail->handle($reservation);
        session()->flash('reservations_flash', __('reservations.flash.confirm_resent'));
    }

    public function render(ExportReservationsAction $export)
    {
        $units = Unit::query()
            ->with(['location', 'category'])
            ->where('is_active', true)
            ->where('allow_reservations', true)
            ->whereHas('category', fn ($q) => $q->where('is_reservable', true))
            ->orderBy('name')
            ->get();

        $reservableUnitCount = $units->count();
        $filters = new ExportReservationsFilterData(
            status: $this->statusFilter,
            locationId: $this->locationFilter !== '' ? (int) $this->locationFilter : null,
            search: trim($this->search),
        );
        $hasFilters = $filters->status !== 'upcoming'
            || $filters->locationId !== null
            || trim($filters->search) !== '';

        $query = Reservation::query()
            ->where('tenant_id', (int) Tenancy::id())
            ->with(['unit.location']);
        $export->applyToQuery($query, $filters);

        $reservations = $query
            ->orderBy($filters->status === 'past' ? 'end_at' : 'start_at', $filters->status === 'past' ? 'desc' : 'asc')
            ->paginate(25);

        $total = $reservations->total();
        $exportQuery = array_filter([
            'status' => $filters->status !== 'upcoming' ? $filters->status : null,
            'location' => $filters->locationId,
            'q' => trim($filters->search) !== '' ? trim($filters->search) : null,
        ], fn ($value) => $value !== null && $value !== '');

        return view('livewire.pages.reservations-index', [
            'reservations' => $reservations,
            'units' => $units,
            'reservableUnitCount' => $reservableUnitCount,
            'locations' => Location::query()->orderBy('name')->get(),
            'calendarReservationsUrl' => route('calendar.index', ['type' => 'reservations']),
            'locationsUrl' => route('locations.index', ['section' => 'categories']),
            'hasFilters' => $hasFilters,
            'total' => $total,
            'showFilters' => $total > 0 || $hasFilters || $reservableUnitCount > 0,
            'exportUrl' => route('reservations.export', $exportQuery),
            'printUrl' => route('reservations.print', $exportQuery),
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
