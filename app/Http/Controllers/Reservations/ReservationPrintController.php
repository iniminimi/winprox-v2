<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reservations;

use App\Actions\Reservations\ExportReservationsAction;
use App\Data\Reservations\ExportReservationsFilterData;
use App\Http\Requests\Reservations\ExportReservationsRequest;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ReservationPrintController
{
    public function __invoke(ExportReservationsRequest $request, ExportReservationsAction $export): View
    {
        Gate::authorize('viewAny', Reservation::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $result = $export->handle((int) $tenant->id, new ExportReservationsFilterData(
            status: (string) ($request->validated('status') ?? 'upcoming'),
            locationId: $request->integer('location') ?: null,
            search: (string) ($request->validated('q') ?? ''),
        ));

        return view('reports.print-reservations', [
            'tenant' => $tenant,
            'reservations' => $result->rows,
            'truncated' => $result->truncated,
            'limit' => $result->limit,
        ]);
    }
}
