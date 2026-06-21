<?php

namespace App\Support\Admin;

final readonly class AdminHealthReport
{
    /**
     * @param  list<AdminHealthIssue>  $issues
     */
    public function __construct(
        public int $totalChecks,
        public int $completeChecks,
        public int $issueCount,
        public array $issues,
    ) {}

    public function isHealthy(): bool
    {
        return $this->issueCount === 0;
    }

    public function percentComplete(): int
    {
        if ($this->totalChecks === 0) {
            return 100;
        }

        return (int) round(($this->completeChecks / $this->totalChecks) * 100);
    }

    public function incompleteFraction(): float
    {
        if ($this->totalChecks === 0) {
            return 0.0;
        }

        return $this->issueCount / $this->totalChecks;
    }
}
