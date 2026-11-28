<?php

declare(strict_types=1);

namespace App\Data\Sense;

final readonly class SenseScreenRef
{
    public function __construct(
        public int $sensePointId,
        public string $place,
    ) {}
}
