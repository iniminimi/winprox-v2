<?php

namespace App\Data\Workers;

readonly class DeleteWorkerImportBatchData
{
    public function __construct(
        public string $importBatchId,
    ) {}
}
