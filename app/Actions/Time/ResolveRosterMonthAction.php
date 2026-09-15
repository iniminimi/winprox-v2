<?php

namespace App\Actions\Time;

use Carbon\Carbon;

class ResolveRosterMonthAction
{
    /**
     * @return array{0: Carbon, 1: Carbon, 2: list<string>}
     */
    public function handle(string $cursor): array
    {
        $start = Carbon::parse($cursor)->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $dates = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return [$start, $end, $dates];
    }
}
