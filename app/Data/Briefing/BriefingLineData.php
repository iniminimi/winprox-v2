<?php

namespace App\Data\Briefing;

final readonly class BriefingLineData
{
    public function __construct(
        public string $locationLabel,
        public string $summary,
        public int $sortKey = PHP_INT_MAX,
        public ?string $locationHint = null,
    ) {}
}
