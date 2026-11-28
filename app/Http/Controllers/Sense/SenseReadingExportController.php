<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sense;

use App\Actions\Sense\ExportSenseReadingsAction;
use App\Actions\Sense\ListSenseReadingScreensAction;
use App\Data\Sense\SenseReadingScreenChoice;
use App\Http\Requests\Sense\ListSenseReadingsRequest;
use App\Models\SensePoint;
use App\Support\Reports\SenseReadingExportTable;
use App\Support\Reports\XlsxDownload;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SenseReadingExportController
{
    public function __invoke(
        ListSenseReadingsRequest $request,
        ListSenseReadingScreensAction $screens,
        ExportSenseReadingsAction $export,
    ): StreamedResponse {
        $choice = $this->choice($request, $screens);
        [$from, $until] = ListSenseReadingsRequest::bounds(
            (string) $request->validated('from'),
            (string) $request->validated('until'),
        );

        $result = $export->handle((int) Tenancy::id(), $choice->sensePointId, $from, $until);
        $rows = SenseReadingExportTable::rows($result->rows);

        if ($result->truncated) {
            $rows = $rows->prepend([
                __('reports.truncated', ['limit' => $result->limit]),
                '', '', '', '',
            ]);
        }

        return XlsxDownload::download(
            __('sense.readings.filename').'-'.now()->format('Y-m-d').'.xlsx',
            SenseReadingExportTable::columns(),
            $rows,
            [false, true, true, true, true],
        );
    }

    private function choice(ListSenseReadingsRequest $request, ListSenseReadingScreensAction $screens): SenseReadingScreenChoice
    {
        Gate::authorize('viewAny', SensePoint::class);

        $catalog = $screens->handle((int) Tenancy::id());
        $screenId = $request->integer('screen');
        $choice = $screenId > 0 ? $catalog->find($screenId) : null;

        if ($choice === null) {
            [$unitId, $clockPointId] = ListSenseReadingsRequest::screenIds();
            foreach ($catalog->screens as $screen) {
                if ($unitId !== null && $screen->unitId === $unitId) {
                    $choice = $screen;
                    break;
                }
                if ($clockPointId !== null && $screen->clockPointId === $clockPointId) {
                    $choice = $screen;
                    break;
                }
            }
        }

        if ($choice === null) {
            abort(404);
        }

        return $choice;
    }
}
