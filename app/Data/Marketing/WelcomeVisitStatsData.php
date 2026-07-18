<?php

declare(strict_types=1);

namespace App\Data\Marketing;

final readonly class WelcomeVisitStatsData
{
    /**
     * @param  array{nl: int, fr: int, en: int, de: int}  $byLocale
     */
    public function __construct(
        public int $uniqueToday,
        public int $uniqueLast7Days,
        public int $uniqueLast30Days,
        public int $uniqueYear2026,
        public array $byLocale,
    ) {}
}
