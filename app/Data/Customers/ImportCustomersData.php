<?php

namespace App\Data\Customers;

readonly class ImportCustomersData
{
    public function __construct(
        public string $filePath,
        public string $originalName,
    ) {}
}
