<?php

namespace App\Data\Locations;

readonly class DeleteLocationImportBatchData
{
    public function __construct(
        public string $importBatchId,
    ) {}
}
