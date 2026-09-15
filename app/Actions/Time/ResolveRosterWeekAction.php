<?php

namespace App\Actions\Time;

use Carbon\Carbon;

class ResolveRosterWeekAction
{
    /**
     * @return array{0: Carbon, 1: Carbon, 2: list<string>}
     */
    public function handle(string $weekStart): array
    {
        $monday = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY)->startOfDay();
        $sunday = $monday->copy()->addDays(6)->endOfDay();
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $monday->copy()->addDays($i)->toDateString();
        }

        return [$monday, $sunday, $dates];
    }
}
