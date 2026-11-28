<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Data\Reports\ListExportResult;
use App\Models\SensePoint;
use App\Models\SenseReading;
use App\Support\Reports\ListExportLimit;
use Carbon\CarbonImmutable;

class ExportSenseReadingsAction
{
    /**
     * @return ListExportResult<SenseReading>
     */
    public function handle(int $tenantId, int $sensePointId, CarbonImmutable $from, CarbonImmutable $until, bool $alarmsOnly = false): ListExportResult
    {
        $limit = ListExportLimit::MAX;

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

        $rows = $query
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $truncated = $rows->count() > $limit;
        if ($truncated) {
            $rows = $rows->take($limit)->values();
        }

        return new ListExportResult($rows, $truncated, $limit);
    }
}
