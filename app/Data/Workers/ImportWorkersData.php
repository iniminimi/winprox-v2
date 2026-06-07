<?php

namespace App\Data\Workers;

readonly class ImportWorkersData
{
    public function __construct(
        public string $filePath,
        public string $originalName,
    ) {}
}
