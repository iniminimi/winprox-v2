<?php

namespace App\Data\Time;

use Carbon\CarbonInterface;

final class TimeRosterViewAttention
{
    public function __construct(
        public int $auditId,
        public int $workerId,
        public string $displayName,
        public ?int $teamId,
        public ?string $teamName,
        public CarbonInterface $viewedAt,
    ) {}
}
