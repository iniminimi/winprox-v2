<?php

namespace App\Actions\Time;

use Carbon\Carbon;

class ResolveRosterPeriodAction
{
    public function __construct(
        private ResolveRosterWeekAction $resolveWeek,
        private ResolveRosterMonthAction $resolveMonth,
    ) {}

    /**
     * @return array{0: Carbon, 1: Carbon, 2: list<string>}
     */
    public function handle(string $cursor, string $period = 'week'): array
    {
        return $period === 'month'
            ? $this->resolveMonth->handle($cursor)
            : $this->resolveWeek->handle($cursor);
    }
}
