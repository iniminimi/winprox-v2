<?php

declare(strict_types=1);

namespace App\Support\Esg;

use App\Enums\EsgIndicatorType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class EsgMeasurementThresholdQuery
{
    /**
     * @param  Builder<\App\Models\EsgMeasurement>  $query
     * @return Builder<\App\Models\EsgMeasurement>
     */
    public static function applyOutsideThresholds(Builder $query): Builder
    {
        $driver = $query->getConnection()->getDriverName();
        $minExtract = self::jsonThresholdExtract($driver, 'min');
        $maxExtract = self::jsonThresholdExtract($driver, 'max');

        return $query->whereExists(function (QueryBuilder $sub) use ($minExtract, $maxExtract): void {
            $sub->selectRaw('1')
                ->from('esg_indicators')
                ->whereColumn('esg_indicators.id', 'esg_measurements.esg_indicator_id')
                ->where('esg_indicators.type', EsgIndicatorType::Numeric->value)
                ->whereNotNull('esg_indicators.thresholds')
                ->whereNotNull('esg_measurements.value_numeric')
                ->where(function (QueryBuilder $outer) use ($minExtract, $maxExtract): void {
                    $outer->where(function (QueryBuilder $inner) use ($minExtract): void {
                        $inner->whereRaw("{$minExtract} IS NOT NULL")
                            ->whereRaw("esg_measurements.value_numeric < {$minExtract}");
                    })->orWhere(function (QueryBuilder $inner) use ($maxExtract): void {
                        $inner->whereRaw("{$maxExtract} IS NOT NULL")
                            ->whereRaw("esg_measurements.value_numeric > {$maxExtract}");
                    });
                });
        });
    }

    private static function jsonThresholdExtract(string $driver, string $key): string
    {
        if ($driver === 'sqlite') {
            return "CAST(json_extract(esg_indicators.thresholds, '$.{$key}') AS REAL)";
        }

        return "CAST(JSON_UNQUOTE(JSON_EXTRACT(esg_indicators.thresholds, '$.{$key}')) AS DECIMAL(20,4))";
    }
}
