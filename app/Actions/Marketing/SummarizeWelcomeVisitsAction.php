<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\WelcomeVisitStatsData;
use App\Models\WelcomeVisit;
use Illuminate\Support\Carbon;

class SummarizeWelcomeVisitsAction
{
    public function handle(?Carbon $now = null): WelcomeVisitStatsData
    {
        $now = ($now ?? now())->timezone(config('app.timezone'));

        $uniqueToday = $this->uniqueCountBetween(
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
        );

        $uniqueLast7Days = $this->uniqueCountBetween(
            $now->copy()->subDays(6)->startOfDay(),
            $now->copy()->endOfDay(),
        );

        $uniqueLast30Days = $this->uniqueCountBetween(
            $now->copy()->subDays(29)->startOfDay(),
            $now->copy()->endOfDay(),
        );

        $yearStart = Carbon::create(2026, 1, 1, 0, 0, 0, config('app.timezone'));
        $yearEnd = Carbon::create(2026, 12, 31, 23, 59, 59, config('app.timezone'));
        assert($yearStart instanceof Carbon && $yearEnd instanceof Carbon);

        $uniqueYear2026 = $this->uniqueCountBetween($yearStart, $yearEnd);

        $byLocale = ['nl' => 0, 'fr' => 0, 'en' => 0, 'de' => 0];
        $localeRows = WelcomeVisit::query()
            ->whereBetween('visited_at', [$yearStart, $yearEnd])
            ->whereIn('locale', array_keys($byLocale))
            ->selectRaw('locale, COUNT(DISTINCT visitor_hash) as aggregate')
            ->groupBy('locale')
            ->pluck('aggregate', 'locale');

        foreach ($byLocale as $locale => $_) {
            $byLocale[$locale] = (int) ($localeRows[$locale] ?? 0);
        }

        return new WelcomeVisitStatsData(
            uniqueToday: $uniqueToday,
            uniqueLast7Days: $uniqueLast7Days,
            uniqueLast30Days: $uniqueLast30Days,
            uniqueYear2026: $uniqueYear2026,
            byLocale: $byLocale,
        );
    }

    private function uniqueCountBetween(Carbon $from, Carbon $to): int
    {
        return (int) WelcomeVisit::query()
            ->whereBetween('visited_at', [$from, $to])
            ->selectRaw('COUNT(DISTINCT visitor_hash) as aggregate')
            ->value('aggregate');
    }
}
