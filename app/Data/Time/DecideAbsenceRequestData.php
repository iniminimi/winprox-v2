<?php

namespace App\Data\Time;

final class DecideAbsenceRequestData
{
    public function __construct(
        public bool $approve,
        public string $reason,
    ) {}
}
