<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sense;

use App\Actions\Sense\ExportSenseReadingsAction;
use App\Actions\Sense\ListSenseReadingScreensAction;
use App\Data\Sense\SenseReadingScreenChoice;
use App\Http\Requests\Sense\ListSenseReadingsRequest;
use App\Models\SensePoint;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class SenseReadingPrintController
{
    public function __invoke(
        ListSenseReadingsRequest $request,
        ListSenseReadingScreensAction $screens,
        ExportSenseReadingsAction $export,
    ): View {
        $choice = $this->choice($request, $screens);
        [$from, $until] = ListSenseReadingsRequest::bounds(
            (string) $request->validated('from'),
            (string) $request->validated('until'),
        );
        $result = $export->handle((int) Tenancy::id(), $choice->sensePointId, $from, $until, $request->boolean('alarms'));

        return view('reports.print-sense', [
            'tenant' => Tenant::query()->findOrFail(Tenancy::id()),
            'place' => $choice->name,
            'readings' => $result->rows,
            'truncated' => $result->truncated,
            'limit' => $result->limit,
        ]);
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
