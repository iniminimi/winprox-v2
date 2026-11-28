<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\SenseReading;
use App\Support\Sense\SenseNumber;
use Illuminate\Support\Collection;

final class SenseReadingExportTable
{
    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
            __('sense.readings.columns.recorded'),
            __('sense.readings.columns.temperature'),
            __('sense.readings.columns.humidity'),
            __('sense.readings.columns.co2'),
            __('sense.readings.columns.voc'),
        ];
    }

    /**
     * @param  Collection<int, SenseReading>  $readings
     * @return Collection<int, list<string>>
     */
    public static function rows(Collection $readings): Collection
    {
        return $readings->map(function (SenseReading $reading): array {
            $at = $reading->recorded_at?->timezone(config('app.timezone'));

            return [
                $at?->format('Y-m-d H:i') ?? '',
                SenseNumber::format((float) $reading->temperature, 1),
                SenseNumber::format((float) $reading->humidity, 0),
                SenseNumber::format((float) $reading->co2, 0),
                SenseNumber::format((float) $reading->voc, 0),
            ];
        });
    }
}
