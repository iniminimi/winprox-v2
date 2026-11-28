<?php

declare(strict_types=1);

namespace App\Data\Sense;

final class SenseRoomView
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $ok,
        public ?string $preset,
        public string $lastLabel,
        public SenseScaleView $temperature,
        public SenseScaleView $humidity,
        public SenseScaleView $co2,
        public SenseScaleView $voc,
        public string $locale,
    ) {}
}
