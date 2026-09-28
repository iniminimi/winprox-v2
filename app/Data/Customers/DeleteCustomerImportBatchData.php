<?php

namespace App\Data\Customers;

readonly class DeleteCustomerImportBatchData
{
    public function __construct(
        public string $importBatchId,
    ) {}
}
