<?php

declare(strict_types=1);

namespace App\Support\Esg;

use Illuminate\Support\Collection;

final readonly class EsgDashboardViewData
{
    /**
     * @param  list<array{
     *     indicator_id: int,
     *     name: string,
     *     value: string,
     *     is_alert: bool,
     *     has_measurement: bool,
     *     recorded_at_label: ?string,
     * }>  $indicatorKpis
     * @param  list<array{id: int, name: string}>  $trendIndicatorOptions
     * @param  list<array{label: string, value: float}>  $trendPoints
     * @param  list<array{
     *     location_id: int,
     *     name: string,
     *     total: float,
     *     total_formatted: string,
     *     measurement_count: int,
     *     detail_url: string,
     *     measurements_url: string,
     * }>  $topLocations
     */
    public function __construct(
        public int $alarmCount,
        public int $thresholdOkPercent,
        public float $thresholdIncompleteFraction,
        public int $thresholdSampleSize,
        public array $indicatorKpis,
        public Collection $recentMeasurements,
        public Collection $alarmMeasurements,
        public Collection $openEsgTasks,
        public bool $showSetup,
        public int $indicatorCount,
        public int $measurementCount,
        public ?int $selectedTrendIndicatorId,
        public ?string $selectedTrendIndicatorName,
        public ?string $selectedTrendUnit,
        public array $trendIndicatorOptions,
        public array $trendPoints,
        public int $trendPeriodDays,
        public array $topLocations,
    ) {}
}
