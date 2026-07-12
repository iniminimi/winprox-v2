<?php

declare(strict_types=1);

namespace App\Actions\Esg;

use App\Enums\EsgIndicatorType;
use App\Enums\TaskStatus;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Task;
use App\Models\Unit;
use App\Support\Esg\EsgMeasurementPresenter;
use App\Support\Esg\EsgPointHistoryViewData;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BuildEsgPointHistoryAction
{
    private const int MEASUREMENT_LIMIT = 50;

    private const int TREND_PERIOD_DAYS = 90;

    public function handle(int $tenantId, int $unitId, ?int $indicatorId = null): ?EsgPointHistoryViewData
    {
        $unit = Unit::query()
            ->where('tenant_id', $tenantId)
            ->with(['location', 'translations'])
            ->find($unitId);

        if ($unit === null) {
            return null;
        }

        $indicatorOptions = $this->indicatorOptions($tenantId, $unitId);
        $selectedIndicator = $this->resolveIndicator($tenantId, $unitId, $indicatorId, $indicatorOptions);

        $measurementsQuery = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->when(
                $selectedIndicator instanceof EsgIndicator,
                fn ($query) => $query->where('esg_indicator_id', $selectedIndicator->id),
            )
            ->with([
                'indicator.translations',
                'location',
                'unit.translations',
                'task',
                'thresholdFollowUpTask',
                'worker',
            ])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(self::MEASUREMENT_LIMIT);

        $measurements = $measurementsQuery->get();
        $periodStart = now()->subDays(self::TREND_PERIOD_DAYS)->startOfDay();
        $trendPoints = $selectedIndicator instanceof EsgIndicator
            ? $this->buildTrendPoints($tenantId, $unitId, $selectedIndicator, $periodStart)
            : [];

        $alarmCount = $measurements
            ->filter(fn (EsgMeasurement $measurement) => EsgMeasurementPresenter::isOutsideThresholds($measurement))
            ->count();

        $openFollowUpCount = $selectedIndicator instanceof EsgIndicator
            ? Task::query()
                ->where('tenant_id', $tenantId)
                ->forApprovedIssue()
                ->whereIn('status', TaskStatus::openValues())
                ->whereHas('issue', fn ($query) => $query
                    ->where('unit_id', $unitId)
                    ->where('esg_indicator_id', $selectedIndicator->id))
                ->whereNotNull('esg_threshold_measurement_id')
                ->count()
            : 0;

        return new EsgPointHistoryViewData(
            unit: $unit,
            locationName: $unit->location?->localizedName() ?? '—',
            selectedIndicatorId: $selectedIndicator?->id,
            selectedIndicatorName: $selectedIndicator?->localizedName(),
            selectedIndicatorUnit: $selectedIndicator?->unit_of_measure,
            indicatorOptions: $indicatorOptions,
            measurements: $measurements,
            trendPoints: $trendPoints,
            trendPeriodDays: self::TREND_PERIOD_DAYS,
            alarmCount: $alarmCount,
            measurementCount: $measurements->count(),
            openFollowUpCount: $openFollowUpCount,
        );
    }

    /** @return list<array{id: int, name: string}> */
    private function indicatorOptions(int $tenantId, int $unitId): array
    {
        $indicatorIds = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->distinct()
            ->pluck('esg_indicator_id');

        if ($indicatorIds->isEmpty()) {
            return [];
        }

        return EsgIndicator::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $indicatorIds)
            ->orderBy('name')
            ->get()
            ->map(fn (EsgIndicator $indicator): array => [
                'id' => $indicator->id,
                'name' => $indicator->localizedName(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: int, name: string}>  $indicatorOptions
     */
    private function resolveIndicator(
        int $tenantId,
        int $unitId,
        ?int $requestedId,
        array $indicatorOptions,
    ): ?EsgIndicator {
        if ($indicatorOptions === []) {
            return null;
        }

        if ($requestedId !== null) {
            $indicator = EsgIndicator::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($requestedId)
                ->first();

            if ($indicator instanceof EsgIndicator) {
                $hasMeasurements = EsgMeasurement::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('esg_indicator_id', $indicator->id)
                    ->exists();

                if ($hasMeasurements) {
                    return $indicator;
                }
            }
        }

        $latestIndicatorId = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->value('esg_indicator_id');

        if ($latestIndicatorId === null) {
            return null;
        }

        return EsgIndicator::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($latestIndicatorId)
            ->first();
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private function buildTrendPoints(
        int $tenantId,
        int $unitId,
        EsgIndicator $indicator,
        CarbonInterface $periodStart,
    ): array {
        $measurements = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
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
}
