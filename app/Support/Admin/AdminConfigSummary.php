<?php

namespace App\Support\Admin;

final readonly class AdminConfigSummary
{
    public function __construct(
        public AdminHealthReport $report,
        public int $inactiveLocationCount,
        public int $inactiveUnitCount,
        public int $inactiveTeamCount,
        public int $inactiveWorkerCount,
        public int $categoryGpsEnabledCount,
        public int $categoryGpsDisabledCount,
        public int $inactiveDocumentCount,
        public int $activeDocumentCount,
    ) {}
}
