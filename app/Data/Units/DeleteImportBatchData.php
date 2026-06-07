<?php

namespace App\Data\Units;

readonly class DeleteImportBatchData
{
    public function __construct(
        public string $importBatchId,
    ) {}
}
