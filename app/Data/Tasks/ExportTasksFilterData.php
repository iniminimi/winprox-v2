<?php

declare(strict_types=1);

namespace App\Data\Tasks;

final readonly class ExportTasksFilterData
{
    public function __construct(
        public string $status = '',
        public ?int $teamId = null,
        public string $priority = '',
        public string $search = '',
        public bool $recurringOnly = false,
    ) {}
}
