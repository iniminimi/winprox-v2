<?php

declare(strict_types=1);

namespace App\Support\Esg;

use App\Models\EsgMeasurement;
use App\Models\Unit;
use Illuminate\Support\Collection;

final readonly class EsgPointHistoryViewData
{
    /**
     * @param  list<array{id: int, name: string}>  $indicatorOptions
     * @param  list<array{label: string, value: float}>  $trendPoints
     */
    public function __construct(
        public Unit $unit,
        public string $locationName,
        public ?int $selectedIndicatorId,
        public ?string $selectedIndicatorName,
        public ?string $selectedIndicatorUnit,
        public array $indicatorOptions,
        public Collection $measurements,
        public array $trendPoints,
        public int $trendPeriodDays,
        public int $alarmCount,
        public int $measurementCount,
        public int $openFollowUpCount,
    ) {}
}
