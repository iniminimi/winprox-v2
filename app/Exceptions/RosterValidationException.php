<?php

namespace App\Exceptions;

use InvalidArgumentException;

class RosterValidationException extends InvalidArgumentException
{
    /**
     * @param  list<array<string, mixed>>  $cells
     */
    public function __construct(
        string $message,
        public array $cells = [],
        public ?int $workerId = null,
        public ?string $date = null,
    ) {
        parent::__construct($message);
    }
}
