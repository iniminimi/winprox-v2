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
        ];
    }
}
