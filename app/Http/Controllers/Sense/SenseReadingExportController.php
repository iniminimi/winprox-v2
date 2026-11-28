<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sense;

use App\Actions\Sense\ExportSenseReadingsAction;
use App\Actions\Sense\FindSensePointForScreenAction;
use App\Http\Requests\Sense\ListSenseReadingsRequest;
use App\Models\SensePoint;
use App\Support\Reports\CsvStreamer;
use App\Support\Reports\SenseReadingExportTable;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SenseReadingExportController
{
    public function __invoke(
        ListSenseReadingsRequest $request,
        FindSensePointForScreenAction $find,
        ExportSenseReadingsAction $export,
    ): StreamedResponse {
        $ref = $this->ref($find);
        [$from, $until] = ListSenseReadingsRequest::bounds(
            (string) $request->validated('from'),
            (string) $request->validated('until'),
        );

        $result = $export->handle((int) Tenancy::id(), $ref->sensePointId, $from, $until);
        $rows = SenseReadingExportTable::rows($result->rows);

        if ($result->truncated) {
            $rows = $rows->prepend([
                __('reports.truncated', ['limit' => $result->limit]),
                '', '', '', '',
            ]);
        }

        return CsvStreamer::download(
            __('sense.readings.filename').'-'.now()->format('Y-m-d').'.csv',
            SenseReadingExportTable::columns(),
            $rows,
        );
    }

    private function ref(FindSensePointForScreenAction $find): \App\Data\Sense\SenseScreenRef
    {
        Gate::authorize('viewAny', SensePoint::class);

        [$unitId, $clockPointId] = ListSenseReadingsRequest::screenIds();
        $ref = $find->handle((int) Tenancy::id(), $unitId, $clockPointId);

        if ($ref === null) {
            abort(404);
        }

        return $ref;
    }
}
