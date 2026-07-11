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
    ) {}
}
