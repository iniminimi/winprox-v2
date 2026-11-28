<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Data\Reports\ListExportResult;
use App\Models\SenseReading;
use App\Support\Reports\ListExportLimit;
use Carbon\CarbonImmutable;

class ExportSenseReadingsAction
{
    /**
     * @return ListExportResult<SenseReading>
     */
    public function handle(int $tenantId, int $sensePointId, CarbonImmutable $from, CarbonImmutable $until): ListExportResult
    {
        $limit = ListExportLimit::MAX;

        $rows = SenseReading::query()
            ->where('tenant_id', $tenantId)
            ->where('sense_point_id', $sensePointId)
            ->where('recorded_at', '>=', $from)
            ->where('recorded_at', '<=', $until)
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
