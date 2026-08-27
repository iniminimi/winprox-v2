<?php

declare(strict_types=1);

namespace App\Actions\UnitChecks;

use App\Data\Reports\ListExportResult;
use App\Data\UnitChecks\ExportUnitChecksFilterData;
use App\Enums\UnitCheckResult;
use App\Models\UnitCheck;
use App\Support\Reports\ListExportLimit;

class ExportUnitChecksAction
{
    /**
     * @return ListExportResult<UnitCheck>
     */
    public function handle(int $tenantId, ExportUnitChecksFilterData $filters): ListExportResult
    {
        $limit = ListExportLimit::MAX;

        $query = UnitCheck::query()
            ->where('tenant_id', $tenantId)
            ->with(['unit', 'location', 'worker', 'team'])
            ->when(
                $filters->result !== 'all' && in_array($filters->result, UnitCheckResult::values(), true),
                fn ($q) => $q->where('result', $filters->result)
            )
            ->when($filters->locationId, fn ($q) => $q->where('location_id', $filters->locationId))
            ->orderByDesc('checked_at')
            ->orderByDesc('id');

        $rows = $query->limit($limit + 1)->get();
        $truncated = $rows->count() > $limit;
        if ($truncated) {
            $rows = $rows->take($limit)->values();
        }

        return new ListExportResult($rows, $truncated, $limit);
    }
}
