<?php

declare(strict_types=1);

namespace App\Data\Sense;

use App\Support\Sense\SensePalette;

final class SenseScaleView
{
    public function __construct(
        public string $metric,
        public string $mode,
        public float $min,
        public float $max,
        public float $step,
        public float $low,
        public ?float $target,
        public float $high,
        public ?float $value,
        public string $valueLabel,
        public string $title,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function alpine(int $pointId): array
    {
        $colors = $this->mode === 'ceiling'
            ? SensePalette::ceiling()
            : SensePalette::band($this->metric);

        return [
            'pointId' => $pointId,
            'metric' => $this->metric,
            'mode' => $this->mode,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
            'low' => $this->low,
            'target' => $this->target,
            'high' => $this->high,
            'colors' => $colors,
            'decimal' => app()->getLocale() === 'en' ? '.' : ',',
            'center' => ($center = config('sense.ranges.'.$this->metric.'.center')) !== null ? (float) $center : null,
        ];
    }

    public function valuePercent(): ?float
    {
        if ($this->value === null || $this->max <= $this->min) {
            return null;
        }

        $clamped = min($this->max, max($this->min, $this->value));

        return (($clamped - $this->min) / ($this->max - $this->min)) * 100;
    }

    public function zoneTrack(): string
    {
        [$yellowAt, $orangeAt, $redAt] = $this->zoneBounds();
        $green = SensePalette::GREEN;
        $yellow = SensePalette::YELLOW;
        $orange = SensePalette::DRY;
        $red = SensePalette::RED;

        return "background: linear-gradient(90deg, {$green} 0%, {$green} {$yellowAt}%, {$yellow} {$yellowAt}%, {$yellow} {$orangeAt}%, {$orange} {$orangeAt}%, {$orange} {$redAt}%, {$red} {$redAt}%, {$red} 100%)";
    }

    /**
     * @return list<array{percent: float, color: string, label: string}>
     */
    public function zoneMarks(): array
    {
        $bounds = $this->zoneBounds();
        $colors = [SensePalette::YELLOW, SensePalette::DRY, SensePalette::RED];
        $zones = config('sense.voc.zones', [100, 150, 250]);
        $marks = [];

        foreach ($zones as $index => $value) {
            $marks[] = [
                'percent' => $bounds[$index],
                'color' => $colors[$index],
                'label' => (string) (int) $value,
            ];
        }

        return $marks;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function zoneBounds(): array
    {
        $zones = config('sense.voc.zones', [100, 150, 250]);
        $span = $this->max - $this->min;

        return array_map(
            fn (int|float $value): float => $span <= 0 ? 0.0 : (($value - $this->min) / $span) * 100,
            array_slice($zones, 0, 3),
        );
    }
}
