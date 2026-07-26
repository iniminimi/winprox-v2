<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Actions\Reservations\UpdateReservationAction;
use App\Data\Reservations\ReservationBookingData;
use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Http\Requests\Reservations\UpdateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Reservation::class);

        return $this->paginated(
            ReservationResource::collection(
                Reservation::query()
                    ->with('unit')
                    ->orderByDesc('start_at')
                    ->paginate(50)
            )
        );
    }

    public function store(StoreReservationRequest $request, CreateReservationAction $createReservation): JsonResponse
    {
        $this->authorize('create', Reservation::class);

        $validated = array_merge($request->validated(), $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
        ]));

        $unit = Unit::query()->with('category')->findOrFail((int) $validated['unit_id']);
        $this->authorize('update', $unit);

        $reservation = $createReservation->handle(
            $unit,
            ReservationBookingData::fromValidated(
                $validated,
                autoConfirm: true,
                createdByUserId: (int) auth()->id(),
            ),
        );

        return $this->item(new ReservationResource($reservation), 201);
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation, UpdateReservationAction $updateReservation): JsonResponse
    {
        $this->authorize('update', $reservation);

        $updated = $updateReservation->handle(
            $reservation,
            ReservationBookingData::fromValidated($request->validated()),
            (int) auth()->id(),
        );

        return $this->item(new ReservationResource($updated));
    }

    public function destroy(Reservation $reservation, CancelReservationAction $cancelReservation): JsonResponse
    {
        $this->authorize('delete', $reservation);
        $cancelReservation->handle($reservation, (int) auth()->id());

        return $this->success(['id' => $reservation->id, 'cancelled' => true]);
    }
}
