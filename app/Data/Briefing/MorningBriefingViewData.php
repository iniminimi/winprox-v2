<?php

namespace App\Data\Briefing;

use App\Models\InternalTeam;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class MorningBriefingViewData
{
    /**
     * @param  Collection<int, InternalTeam>  $teams
     * @param  Collection<int, BriefingLineData>  $unitLines
     * @param  Collection<int, BriefingLineData>  $generalLines
     */
    public function __construct(
        public ?InternalTeam $team,
        public Collection $teams,
        public Carbon $date,
        public Collection $unitLines,
        public Collection $generalLines,
        public int $lineCount,
        public bool $openTasksOnly,
    ) {}

    public function isReady(): bool
    {
        return $this->team !== null;
    }
}
