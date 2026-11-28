<?php

declare(strict_types=1);

namespace App\Support\Sense;

use App\Enums\SenseAttentionDirection;
use App\Enums\SenseMetric;
use App\Models\SensePoint;
use App\Models\SenseReading;
use App\Models\Unit;
use Carbon\CarbonInterface;

final class SenseBands
{
    public static function roomLabel(Unit $unit): string
    {
        $unit->loadMissing('location');

        if ($unit->is_site_unit && filled($unit->location?->name)) {
            return (string) $unit->location->name;
        }

        return $unit->localizedName();
    }

    public static function choiceLabel(Unit $unit): string
    {
        $unit->loadMissing('location');
        $name = self::roomLabel($unit);
        $location = $unit->location?->name;

        if ($unit->is_site_unit || ! filled($location) || $location === $name) {
            return $name;
        }

        return $location.' — '.$name;
    }

    public static function outsideLimits(SenseReading $reading, SensePoint $point): bool
    {
        $temperature = round((float) $reading->temperature, 1);
        if ($temperature < round((float) $point->temperature_low, 1) || $temperature > round((float) $point->temperature_high, 1)) {
            return true;
        }

        $humidity = round((float) $reading->humidity, 1);
        if ($humidity < round((float) $point->humidity_low, 1) || $humidity > round((float) $point->humidity_high, 1)) {
            return true;
        }

        if ((int) $reading->co2 > (int) $point->co2_high || (int) $reading->voc > (int) $point->voc_high) {
            return true;
        }

        return false;
    }

    /**
     * @return list<array{metric: SenseMetric, direction: SenseAttentionDirection, value: ?float, limit: ?float, recorded_at: ?CarbonInterface}>
     */
    public static function attentions(SensePoint $point): array
    {
        if ($point->last_recorded_at === null) {
            return [self::row(SenseMetric::Stale, SenseAttentionDirection::Missing, null, null, null)];
        }

        $rows = [];
        $temperature = round((float) $point->last_temperature, 1);
        if ($temperature < round((float) $point->temperature_low, 1)) {
            $rows[] = self::row(SenseMetric::Temperature, SenseAttentionDirection::Below, $temperature, (float) $point->temperature_low, $point->last_recorded_at);
        } elseif ($temperature > round((float) $point->temperature_high, 1)) {
            $rows[] = self::row(SenseMetric::Temperature, SenseAttentionDirection::Above, $temperature, (float) $point->temperature_high, $point->last_recorded_at);
        }

        $humidity = round((float) $point->last_humidity, 1);
        if ($humidity < round((float) $point->humidity_low, 1)) {
            $rows[] = self::row(SenseMetric::Humidity, SenseAttentionDirection::Below, $humidity, (float) $point->humidity_low, $point->last_recorded_at);
        } elseif ($humidity > round((float) $point->humidity_high, 1)) {
            $rows[] = self::row(SenseMetric::Humidity, SenseAttentionDirection::Above, $humidity, (float) $point->humidity_high, $point->last_recorded_at);
        }

        $co2 = (int) $point->last_co2;
        if ($co2 > (int) $point->co2_high) {
            $rows[] = self::row(SenseMetric::Co2, SenseAttentionDirection::Above, (float) $co2, (float) $point->co2_high, $point->last_recorded_at);
        }

        $voc = (int) $point->last_voc;
        if ($voc > (int) $point->voc_high && self::vocHolds($point, (int) $point->voc_high)) {
            $rows[] = self::row(SenseMetric::Voc, SenseAttentionDirection::Above, (float) $voc, (float) $point->voc_high, $point->last_recorded_at);
        }

        $staleAfter = (int) config('sense.stale_after_minutes', 15);
        if ($rows === [] && $point->last_recorded_at->lt(now()->subMinutes($staleAfter))) {
            $rows[] = self::row(SenseMetric::Stale, SenseAttentionDirection::Stale, null, null, $point->last_recorded_at);
        }

        return $rows;
    }

    public static function sentence(string $room, SenseMetric $metric, SenseAttentionDirection $direction, ?float $value, ?float $limit, ?CarbonInterface $recordedAt): string
    {
        if ($direction === SenseAttentionDirection::Missing) {
            return __('sense.attention.missing', ['room' => $room]);
        }

        if ($direction === SenseAttentionDirection::Stale) {
            return __('sense.attention.stale', [
                'room' => $room,
                'when' => self::whenLabel($recordedAt),
            ]);
        }

        return __('sense.attention.'.$direction->value, [
            'room' => $room,
            'metric' => __($metric->labelKey()),
            'value' => SenseNumber::withUnit($metric, (float) $value),
            'limit' => SenseNumber::withUnit($metric, (float) $limit),
        ]);
    }

    public static function whenLabel(?CarbonInterface $at): string
    {
        if ($at === null) {
            return '';
        }

        $local = $at->timezone(config('app.timezone'));
        $time = $local->format('H:i');

        if ($local->isToday()) {
            return __('sense.attention.today_at', ['time' => $time]);
        }

        if ($local->isYesterday()) {
            return __('sense.attention.yesterday_at', ['time' => $time]);
        }

        return __('sense.attention.date_at', [
            'date' => $local->format('d-m-Y'),
            'time' => $time,
        ]);
    }

    private static function vocHolds(SensePoint $point, int $limit): bool
    {
        $minutes = (int) config('sense.voc.attention_minutes', 30);
        $since = now()->subMinutes($minutes);

        $readings = SenseReading::query()
            ->where('sense_point_id', $point->id)
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get(['voc', 'recorded_at']);

        if ($readings->isEmpty()) {
            return false;
        }

        foreach ($readings as $reading) {
            if ((int) $reading->voc <= $limit) {
                return false;
            }
        }

        return $readings->first()->recorded_at->lte($since);
    }

    /**
     * @return array{metric: SenseMetric, direction: SenseAttentionDirection, value: ?float, limit: ?float, recorded_at: ?CarbonInterface}
     */
    private static function row(
        SenseMetric $metric,
        SenseAttentionDirection $direction,
        ?float $value,
        ?float $limit,
        ?CarbonInterface $recordedAt,
    ): array {
        return [
            'metric' => $metric,
            'direction' => $direction,
            'value' => $value,
            'limit' => $limit,
            'recorded_at' => $recordedAt,
        ];
    }
}
