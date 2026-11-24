<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Data\Sense\SenseRoomView;
use App\Data\Sense\SenseScaleView;
use App\Enums\SenseMetric;
use App\Models\SensePoint;
use App\Support\Sense\SenseBands;
use App\Support\Sense\SenseNumber;
use Illuminate\Support\Collection;

class ListSenseRoomsAction
{
    /**
     * @return list<SenseRoomView>
     */
    public function handle(int $tenantId): array
    {
        /** @var Collection<int, SensePoint> $points */
        $points = SensePoint::query()
            ->where('tenant_id', $tenantId)
            ->with(['unit.location'])
            ->get();

        $rooms = $points->map(function (SensePoint $point): SenseRoomView {
            $unit = $point->unit;
            $name = $unit !== null ? SenseBands::roomLabel($unit) : '';
            $ok = SenseBands::attentions($point) === [];

            return new SenseRoomView(
                id: (int) $point->id,
                name: $name,
                ok: $ok,
                lastLabel: $this->lastLabel($point),
                temperature: $this->band($point, SenseMetric::Temperature),
                humidity: $this->band($point, SenseMetric::Humidity),
                co2: $this->ceiling($point, SenseMetric::Co2),
                voc: $this->ceiling($point, SenseMetric::Voc),
            );
        })->sort(function (SenseRoomView $a, SenseRoomView $b): int {
            if ($a->ok !== $b->ok) {
                return $a->ok <=> $b->ok;
            }

            return strcasecmp($a->name, $b->name);
        })->values();

        return $rooms->all();
    }

    private function lastLabel(SensePoint $point): string
    {
        if ($point->last_recorded_at === null) {
            return __('sense.card.last_none');
        }

        return __('sense.card.last', ['when' => SenseBands::whenLabel($point->last_recorded_at)]);
    }

    private function band(SensePoint $point, SenseMetric $metric): SenseScaleView
    {
        $range = config('sense.ranges.'.$metric->value);
        $lowKey = $metric->value.'_low';
        $targetKey = $metric->value.'_target';
        $highKey = $metric->value.'_high';
        $valueKey = 'last_'.$metric->value;
        $value = $point->{$valueKey};

        return new SenseScaleView(
            metric: $metric->value,
            mode: 'band',
            min: (float) $range['min'],
            max: (float) $range['max'],
            step: (float) $range['step'],
            low: (float) $point->{$lowKey},
            target: (float) $point->{$targetKey},
            high: (float) $point->{$highKey},
            value: $value !== null ? (float) $value : null,
            valueLabel: $value !== null ? SenseNumber::withUnit($metric, (float) $value) : '—',
            title: __($metric->labelKey()),
        );
    }

    private function ceiling(SensePoint $point, SenseMetric $metric): SenseScaleView
    {
        $range = config('sense.ranges.'.$metric->value);
        $highKey = $metric->value.'_high';
        $valueKey = 'last_'.$metric->value;
        $value = $point->{$valueKey};

        return new SenseScaleView(
            metric: $metric->value,
            mode: 'ceiling',
            min: (float) $range['min'],
            max: (float) $range['max'],
            step: (float) $range['step'],
            low: (float) $range['min'],
            target: null,
            high: (float) $point->{$highKey},
            value: $value !== null ? (float) $value : null,
            valueLabel: $value !== null ? SenseNumber::withUnit($metric, (float) $value) : '—',
            title: __($metric->labelKey()),
        );
    }
}
