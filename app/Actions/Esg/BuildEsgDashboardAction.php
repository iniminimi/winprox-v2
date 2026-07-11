<?php

declare(strict_types=1);

namespace App\Actions\Esg;

use App\Enums\EsgIndicatorType;
use App\Enums\TaskStatus;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Task;
use App\Support\Esg\EsgDashboardViewData;
use App\Support\Esg\EsgMeasurementPresenter;
use Illuminate\Support\Collection;

class BuildEsgDashboardAction
{
    private const int INDICATOR_KPI_LIMIT = 6;

    private const int RECENT_MEASUREMENT_LIMIT = 12;

    private const int ALARM_LIMIT = 8;

    private const int OPEN_TASK_LIMIT = 8;

    private const int THRESHOLD_SAMPLE_LIMIT = 30;

    public function handle(int $tenantId): EsgDashboardViewData
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
}
