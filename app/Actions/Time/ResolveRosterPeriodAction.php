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
    public function handle(string $cursor, string $period = 'week', bool $includeWeekends = true): array
    {
        [$start, $end, $dates] = $period === 'month'
            ? $this->resolveMonth->handle($cursor)
            : $this->resolveWeek->handle($cursor);

        if (! $includeWeekends) {
            $dates = array_values(array_filter(
                $dates,
                fn (string $date) => ! Carbon::parse($date)->isWeekend(),
            ));
        }

        return [$start, $end, $dates];
    }
}
