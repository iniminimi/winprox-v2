<?php

declare(strict_types=1);

namespace App\Data\Time;

use App\Enums\ClockDisplayPinStatus;

/**
 * Resultaat van een PIN-klokpoging vanaf een klokscherm — de device-API
 * mapt dit 1-op-1 op de JSON-response.
 */
final readonly class ClockDisplayPinResult
{
    public function __construct(
        public ClockDisplayPinStatus $status,
        public ?string $workerName = null,
        public ?string $clockedAt = null,
        public ?int $retryAfterSeconds = null,
    ) {}
}
