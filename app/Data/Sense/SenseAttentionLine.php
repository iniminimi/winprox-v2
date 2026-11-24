<?php

declare(strict_types=1);

namespace App\Data\Sense;

final class SenseAttentionLine
{
    public function __construct(
        public int $sensePointId,
        public string $room,
        public string $detail,
        public string $url,
    ) {}
}
