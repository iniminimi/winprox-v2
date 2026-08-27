<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reservations;

use App\Actions\Reservations\ExportReservationsAction;
use App\Data\Reservations\ExportReservationsFilterData;
use App\Http\Requests\Reservations\ExportReservationsRequest;
use App\Models\Reservation;
use App\Support\Reports\CsvStreamer;
use App\Support\Reports\ReservationExportTable;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReservationExportController
{
    public function __invoke(ExportReservationsRequest $request, ExportReservationsAction $export): StreamedResponse
    {
        Gate::authorize('viewAny', Reservation::class);

        $result = $export->handle((int) Tenancy::id(), $this->filters($request));
        $rows = ReservationExportTable::rows($result->rows);

        if ($result->truncated) {
            $rows = $rows->prepend([
                __('reports.truncated', ['limit' => $result->limit]),
                '', '', '', '', '', '', '',
            ]);
        }

        return CsvStreamer::download(
            __('reports.reservations.filename').'-'.now()->format('Y-m-d').'.csv',
            ReservationExportTable::columns(),
            $rows,
        );
    }

    private function filters(ExportReservationsRequest $request): ExportReservationsFilterData
    {
        return new ExportReservationsFilterData(
            status: (string) ($request->validated('status') ?? 'upcoming'),
            locationId: $request->integer('location') ?: null,
        );
    }
}
