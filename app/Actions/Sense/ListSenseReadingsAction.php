<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Models\SensePoint;
use App\Models\SenseReading;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSenseReadingsAction
{
    public function handle(int $tenantId, int $sensePointId, CarbonImmutable $from, CarbonImmutable $until, int $page, bool $alarmsOnly = false): LengthAwarePaginator
    {
        $query = SenseReading::query()
            ->where('tenant_id', $tenantId)
            ->where('sense_point_id', $sensePointId)
            ->where('recorded_at', '>=', $from)
            ->where('recorded_at', '<=', $until)
            ->with('point');

        if ($alarmsOnly) {
            $point = SensePoint::query()->where('tenant_id', $tenantId)->find($sensePointId);
            if ($point instanceof SensePoint) {
                $query->outsideBands($point);
            }
        }

        return $query
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(perPage: 50, page: max(1, $page));
    }
}
