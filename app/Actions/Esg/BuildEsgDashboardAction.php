<?php

declare(strict_types=1);

namespace App\Actions\Esg;

use App\Enums\EsgIndicatorCategory;
use App\Enums\EsgIndicatorType;
use App\Enums\TaskStatus;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Location;
use App\Models\Task;
use App\Support\Esg\EsgDashboardViewData;
use App\Support\Esg\EsgMeasurementPresenter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BuildEsgDashboardAction
{
    private const int INDICATOR_KPI_LIMIT = 6;

    private const int RECENT_MEASUREMENT_LIMIT = 12;

    private const int ALARM_LIMIT = 8;

    private const int OPEN_TASK_LIMIT = 8;

    private const int THRESHOLD_SAMPLE_LIMIT = 30;

    private const int TOP_LOCATION_LIMIT = 10;

    private const int TREND_PERIOD_DAYS = 30;

    private const float COMPLIANCE_THRESHOLD_WEIGHT = 0.6;

    private const float COMPLIANCE_COVERAGE_WEIGHT = 0.4;

    public function handle(int $tenantId, ?int $trendIndicatorId = null): EsgDashboardViewData
    {
        $indicatorCount = EsgIndicator::query()
            ->where('tenant_id', $tenantId)
            ->count();

        $measurementCount = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->count();

        $numericIndicators = EsgIndicator::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('type', EsgIndicatorType::Numeric)
            ->orderBy('name')
            ->limit(self::INDICATOR_KPI_LIMIT)
            ->get();

        $indicatorKpis = $numericIndicators
            ->map(fn (EsgIndicator $indicator) => $this->buildIndicatorKpi($indicator))
            ->values()
            ->all();

        $recentMeasurements = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'indicator.translations',
                'location',
                'unit.translations',
                'task',
                'worker',
                'correctsMeasurement.indicator',
            ])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_MEASUREMENT_LIMIT)
            ->get();

        $thresholdSample = $this->thresholdSample($tenantId);
        $alarmMeasurements = $thresholdSample
            ->filter(fn (EsgMeasurement $measurement) => EsgMeasurementPresenter::isOutsideThresholds($measurement))
            ->take(self::ALARM_LIMIT)
            ->values();

        $alarmCount = $thresholdSample
            ->filter(fn (EsgMeasurement $measurement) => EsgMeasurementPresenter::isOutsideThresholds($measurement))
            ->count();

        $thresholdSampleSize = $thresholdSample->count();
        $thresholdOkPercent = $thresholdSampleSize === 0
            ? 100
            : (int) round((($thresholdSampleSize - $alarmCount) / $thresholdSampleSize) * 100);
        $thresholdIncompleteFraction = $thresholdSampleSize === 0
            ? 0.0
            : $alarmCount / $thresholdSampleSize;

        $openEsgTasks = Task::query()
            ->where('tenant_id', $tenantId)
            ->forApprovedIssue()
            ->whereIn('status', TaskStatus::openValues())
            ->whereHas('issue', fn ($query) => $query->whereNotNull('esg_indicator_id'))
            ->with([
                'issue.esgIndicator.translations',
                'issue.location',
                'issue.unit.translations',
            ])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->limit(self::OPEN_TASK_LIMIT)
            ->get();

        $trendIndicatorOptions = $this->trendIndicatorOptions($tenantId);
        $selectedTrendIndicator = $this->resolveTrendIndicator($tenantId, $trendIndicatorId);
        $periodStart = now()->subDays(self::TREND_PERIOD_DAYS)->startOfDay();
        $trendPoints = $selectedTrendIndicator instanceof EsgIndicator
            ? $this->buildTrendPoints($tenantId, $selectedTrendIndicator, $periodStart)
            : [];
        $topLocations = $selectedTrendIndicator instanceof EsgIndicator
            ? $this->buildTopLocations($tenantId, $selectedTrendIndicator, $periodStart)
            : [];

        $compliance = $this->buildComplianceScore($tenantId, $periodStart);
        $categoryBreakdown = $this->buildCategorySegments($tenantId, $periodStart);

        return new EsgDashboardViewData(
            alarmCount: $alarmCount,
            thresholdOkPercent: $thresholdOkPercent,
            thresholdIncompleteFraction: $thresholdIncompleteFraction,
            thresholdSampleSize: $thresholdSampleSize,
            indicatorKpis: $indicatorKpis,
            recentMeasurements: $recentMeasurements,
            alarmMeasurements: $alarmMeasurements,
            openEsgTasks: $openEsgTasks,
            showSetup: $indicatorCount === 0 || $measurementCount === 0,
            indicatorCount: $indicatorCount,
            measurementCount: $measurementCount,
            selectedTrendIndicatorId: $selectedTrendIndicator?->id,
            selectedTrendIndicatorName: $selectedTrendIndicator?->localizedName(),
            selectedTrendUnit: $selectedTrendIndicator?->unit_of_measure,
            trendIndicatorOptions: $trendIndicatorOptions,
            trendPoints: $trendPoints,
            trendPeriodDays: self::TREND_PERIOD_DAYS,
            topLocations: $topLocations,
            showComplianceScore: $compliance['show'],
            complianceScore: $compliance['score'],
            complianceThresholdPercent: $compliance['threshold_percent'],
            complianceCoveragePercent: $compliance['coverage_percent'],
            complianceIncompleteFraction: $compliance['incomplete_fraction'],
            categorySegments: $categoryBreakdown['segments'],
            categoryMeasurementTotal: $categoryBreakdown['total'],
        );
    }

    /**
     * @return array{
     *     indicator_id: int,
     *     name: string,
     *     value: string,
     *     is_alert: bool,
     *     has_measurement: bool,
     *     recorded_at_label: ?string,
     * }
     */
    private function buildIndicatorKpi(EsgIndicator $indicator): array
    {
        $latestMeasurement = EsgMeasurement::query()
            ->where('esg_indicator_id', $indicator->id)
            ->with('indicator.translations')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        if ($latestMeasurement === null) {
            return [
                'indicator_id' => $indicator->id,
                'name' => $indicator->localizedName(),
                'value' => '—',
                'is_alert' => false,
                'has_measurement' => false,
                'recorded_at_label' => null,
            ];
        }

        return [
            'indicator_id' => $indicator->id,
            'name' => $indicator->localizedName(),
            'value' => EsgMeasurementPresenter::displayValue($latestMeasurement),
            'is_alert' => EsgMeasurementPresenter::isOutsideThresholds($latestMeasurement),
            'has_measurement' => true,
            'recorded_at_label' => $latestMeasurement->recorded_at?->format('d-m-Y H:i'),
        ];
    }

    /** @return Collection<int, EsgMeasurement> */
    private function thresholdSample(int $tenantId): Collection
    {
        return EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('indicator', fn ($query) => $query
                ->where('type', EsgIndicatorType::Numeric)
                ->whereNotNull('thresholds'))
            ->with(['indicator.translations', 'location', 'unit.translations', 'task'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(self::THRESHOLD_SAMPLE_LIMIT)
            ->get();
    }

    /** @return list<array{id: int, name: string}> */
    private function trendIndicatorOptions(int $tenantId): array
    {
        return EsgIndicator::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('type', EsgIndicatorType::Numeric)
            ->orderBy('name')
            ->get()
            ->map(fn (EsgIndicator $indicator): array => [
                'id' => $indicator->id,
                'name' => $indicator->localizedName(),
            ])
            ->values()
            ->all();
    }

    private function resolveTrendIndicator(int $tenantId, ?int $requestedId): ?EsgIndicator
    {
        if ($requestedId !== null) {
            $indicator = EsgIndicator::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('type', EsgIndicatorType::Numeric)
                ->whereKey($requestedId)
                ->first();

            if ($indicator instanceof EsgIndicator) {
                return $indicator;
            }
        }

        $latestIndicatorId = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('indicator', fn ($query) => $query
                ->where('type', EsgIndicatorType::Numeric)
                ->where('is_active', true))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->value('esg_indicator_id');

        if ($latestIndicatorId !== null) {
            $indicator = EsgIndicator::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($latestIndicatorId)
                ->first();

            if ($indicator instanceof EsgIndicator) {
                return $indicator;
            }
        }

        return EsgIndicator::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('type', EsgIndicatorType::Numeric)
            ->orderBy('name')
            ->first();
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private function buildTrendPoints(int $tenantId, EsgIndicator $indicator, CarbonInterface $periodStart): array
    {
        $measurements = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->where('esg_indicator_id', $indicator->id)
            ->where('recorded_at', '>=', $periodStart)
            ->whereNotNull('value_numeric')
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'value_numeric']);

        if ($measurements->isEmpty()) {
            return [];
        }

        return $measurements
            ->groupBy(fn (EsgMeasurement $measurement): string => $measurement->recorded_at->format('Y-m-d'))
            ->map(fn (Collection $group): float => (float) $group->sum(fn (EsgMeasurement $measurement): float => (float) $measurement->value_numeric))
            ->sortKeys()
            ->map(fn (float $total, string $day): array => [
                'label' => \Illuminate\Support\Carbon::parse($day)->format('d-m'),
                'value' => $total,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     location_id: int,
     *     name: string,
     *     total: float,
     *     total_formatted: string,
     *     measurement_count: int,
     *     detail_url: string,
     *     measurements_url: string,
     * }>
     */
    private function buildTopLocations(int $tenantId, EsgIndicator $indicator, CarbonInterface $periodStart): array
    {
        $measurements = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->where('esg_indicator_id', $indicator->id)
            ->where('recorded_at', '>=', $periodStart)
            ->whereNotNull('location_id')
            ->whereNotNull('value_numeric')
            ->with('location')
            ->get(['location_id', 'value_numeric']);

        if ($measurements->isEmpty()) {
            return [];
        }

        $rows = $measurements
            ->groupBy('location_id')
            ->map(function (Collection $group) use ($indicator, $tenantId): ?array {
                $first = $group->first();
                if (! $first instanceof EsgMeasurement) {
                    return null;
                }

                $location = $first->location;
                if (! $location instanceof Location) {
                    return null;
                }

                $total = (float) $group->sum(fn (EsgMeasurement $measurement): float => (float) $measurement->value_numeric);

                return [
                    'location_id' => (int) $location->id,
                    'name' => $location->localizedName(),
                    'total' => $total,
                    'total_formatted' => $this->formatNumericTotal($total, $indicator->unit_of_measure),
                    'measurement_count' => $group->count(),
                    'detail_url' => route('locations.show', $location),
                    'measurements_url' => route('esg.measurements.index', [
                        'indicator' => $indicator->id,
                        'location' => $location->id,
                    ]),
                ];
            })
            ->filter()
            ->sortByDesc('total')
            ->take(self::TOP_LOCATION_LIMIT)
            ->values()
            ->all();

        return $rows;
    }

    private function formatNumericTotal(float $value, ?string $unitOfMeasure): string
    {
        $formatted = rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',');

        return filled($unitOfMeasure) ? "{$formatted} {$unitOfMeasure}" : $formatted;
    }

    /**
     * @return array{
     *     show: bool,
     *     score: ?int,
     *     threshold_percent: ?int,
     *     coverage_percent: ?int,
     *     incomplete_fraction: float,
     * }
     */
    private function buildComplianceScore(int $tenantId, CarbonInterface $periodStart): array
    {
        $thresholdMeasurements = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->where('recorded_at', '>=', $periodStart)
            ->whereHas('indicator', fn ($query) => $query
                ->where('type', EsgIndicatorType::Numeric)
                ->whereNotNull('thresholds'))
            ->with('indicator')
            ->get();

        $thresholdPercent = null;
        if ($thresholdMeasurements->isNotEmpty()) {
            $okCount = $thresholdMeasurements
                ->filter(fn (EsgMeasurement $measurement) => ! EsgMeasurementPresenter::isOutsideThresholds($measurement))
                ->count();
            $thresholdPercent = (int) round(($okCount / $thresholdMeasurements->count()) * 100);
        }

        $activeNumericIndicatorIds = EsgIndicator::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('type', EsgIndicatorType::Numeric)
            ->pluck('id');

        $coveragePercent = null;
        if ($activeNumericIndicatorIds->isNotEmpty()) {
            $indicatorsWithReadings = EsgMeasurement::query()
                ->where('tenant_id', $tenantId)
                ->where('recorded_at', '>=', $periodStart)
                ->whereIn('esg_indicator_id', $activeNumericIndicatorIds)
                ->distinct()
                ->count('esg_indicator_id');

            $coveragePercent = (int) round(($indicatorsWithReadings / $activeNumericIndicatorIds->count()) * 100);
        }

        $score = null;
        if ($thresholdPercent !== null && $coveragePercent !== null) {
            $score = (int) round(
                (self::COMPLIANCE_THRESHOLD_WEIGHT * $thresholdPercent)
                + (self::COMPLIANCE_COVERAGE_WEIGHT * $coveragePercent)
            );
        } elseif ($thresholdPercent !== null) {
            $score = $thresholdPercent;
        } elseif ($coveragePercent !== null) {
            $score = $coveragePercent;
        }

        return [
            'show' => $score !== null,
            'score' => $score,
            'threshold_percent' => $thresholdPercent,
            'coverage_percent' => $coveragePercent,
            'incomplete_fraction' => $score === null ? 0.0 : (100 - $score) / 100,
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     segments: list<array{key: string, label: string, count: int, percent: float}>,
     * }
     */
    private function buildCategorySegments(int $tenantId, CarbonInterface $periodStart): array
    {
        $measurements = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->where('recorded_at', '>=', $periodStart)
            ->with('indicator')
            ->get(['esg_indicator_id']);

        $total = $measurements->count();
        if ($total === 0) {
            return [
                'total' => 0,
                'segments' => [],
            ];
        }

        $segments = $measurements
            ->groupBy(function (EsgMeasurement $measurement): string {
                $category = $measurement->indicator?->category;

                return $category instanceof EsgIndicatorCategory
                    ? $category->value
                    : EsgIndicatorCategory::Other->value;
            })
            ->map(function (Collection $group, string $key): array {
                $category = EsgIndicatorCategory::from($key);

                return [
                    'key' => $key,
                    'label' => __($category->labelKey()),
                    'count' => $group->count(),
                    'percent' => 0.0,
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->map(function (array $segment) use ($total): array {
                $segment['percent'] = round(($segment['count'] / $total) * 100, 1);

                return $segment;
            })
            ->all();

        return [
            'total' => $total,
            'segments' => $segments,
        ];
    }
}
