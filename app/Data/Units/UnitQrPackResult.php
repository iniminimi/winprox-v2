<?php

declare(strict_types=1);

namespace App\Data\Units;

final readonly class UnitQrPackResult
{
    public function __construct(
        public string $binary,
        public string $filename,
    ) {}
}
