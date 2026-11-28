<?php

declare(strict_types=1);

namespace App\Data\Sense;

final class SenseAttentionBoard
{
    /**
     * @param  list<SenseAttentionLine>  $lines
     */
    public function __construct(
        public array $lines,
        public ?string $readingsUrl,
    ) {}
}
