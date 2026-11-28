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
        app(SyncSenseSeasonAction::class)->handleTenant($tenantId);
        /** @var Collection<int, SensePoint> $points */
        $points = SensePoint::query()
            ->where('tenant_id', $tenantId)
            ->with(['unit.location'])
            ->get();

        $rooms = $points->map(fn (SensePoint $point): SenseRoomView => $this->view($point))->sort(function (SenseRoomView $a, SenseRoomView $b): int {
            if ($a->ok !== $b->ok) {
                return $a->ok <=> $b->ok;
            }

            return strcasecmp($a->name, $b->name);
        })->values();

        return $rooms->all();
    }

    public function view(SensePoint $point): SenseRoomView
    {
        $point->loadMissing(['unit.location', 'screen.clockPoint']);
        $name = $point->unit !== null
            ? SenseBands::roomLabel($point->unit)
            : (string) ($point->screen?->clockPoint?->name ?? '');

        return new SenseRoomView(
            id: (int) $point->id,
            name: $name,
            ok: SenseBands::attentions($point) === [],
            preset: $this->presetKey($point),
            lastLabel: $this->lastLabel($point),
            temperature: $this->band($point, SenseMetric::Temperature),
            humidity: $this->band($point, SenseMetric::Humidity),
            co2: $this->ceiling($point, SenseMetric::Co2),
            voc: $this->scale($point, SenseMetric::Voc),
            locale: in_array($point->display_locale, config('locales.supported'), true)
                ? (string) $point->display_locale
                : (string) config('locales.default', 'nl'),
        );
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
            valueLabel: $value !== null ? SenseNumber::onScale($metric, (float) $value) : '—',
            title: __($metric->labelKey()),
        );
    }

    private function presetKey(SensePoint $point): ?string
    {
        foreach (config('sense.presets', []) as $key => $preset) {
            if ($this->same($point->temperature_low, $preset['temperature']['low'])
                && $this->same($point->temperature_target, $preset['temperature']['target'])
                && $this->same($point->temperature_high, $preset['temperature']['high'])
                && $this->same($point->humidity_low, $preset['humidity']['low'])
                && $this->same($point->humidity_target, $preset['humidity']['target'])
                && $this->same($point->humidity_high, $preset['humidity']['high'])) {
                return (string) $key;
            }
        }

        return null;
    }

    private function same(mixed $stored, mixed $preset): bool
    {
        return round((float) $stored, 1) === round((float) $preset, 1);
    }

    private function scale(SensePoint $point, SenseMetric $metric): SenseScaleView
    {
        $range = config('sense.ranges.'.$metric->value);
        $value = $point->{'last_'.$metric->value};

        return new SenseScaleView(
            metric: $metric->value,
            mode: 'scale',
            min: (float) $range['min'],
            max: (float) $range['max'],
            step: (float) $range['step'],
            low: (float) $range['min'],
            target: null,
            high: (float) $point->{$metric->value.'_high'},
            value: $value !== null ? (float) $value : null,
            valueLabel: $value !== null ? SenseNumber::onScale($metric, (float) $value) : '—',
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
            valueLabel: $value !== null ? SenseNumber::onScale($metric, (float) $value) : '—',
            title: __($metric->labelKey()),
        );
    }
}
