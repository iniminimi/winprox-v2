<?php

namespace App\Actions\Locations;

use App\Models\UnitBulkBatch;
use App\Support\Units\UnitBulkBatchRegistry;

class DeleteUnitBulkBatchAction
{
    /**
     * @return array{deleted: int, skipped: int}
     */
    public function handle(UnitBulkBatch $batch): array
    {
        return UnitBulkBatchRegistry::deleteDeletableUnitsInBatch($batch);
    }
}
