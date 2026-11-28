<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sense;

use App\Actions\Sense\ExportSenseReadingsAction;
use App\Actions\Sense\FindSensePointForScreenAction;
use App\Data\Sense\SenseScreenRef;
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
        FindSensePointForScreenAction $find,
        ExportSenseReadingsAction $export,
    ): View {
        $ref = $this->ref($find);
        [$from, $until] = ListSenseReadingsRequest::bounds(
            (string) $request->validated('from'),
            (string) $request->validated('until'),
        );
        $result = $export->handle((int) Tenancy::id(), $ref->sensePointId, $from, $until);

        return view('reports.print-sense', [
            'tenant' => Tenant::query()->findOrFail(Tenancy::id()),
            'place' => $ref->place,
            'readings' => $result->rows,
            'truncated' => $result->truncated,
            'limit' => $result->limit,
        ]);
    }

    private function ref(FindSensePointForScreenAction $find): SenseScreenRef
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
