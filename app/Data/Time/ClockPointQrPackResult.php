<?php

declare(strict_types=1);

namespace App\Data\Time;

final readonly class ClockPointQrPackResult
{
    public function __construct(
        public string $binary,
        public string $filename,
    ) {}
}
