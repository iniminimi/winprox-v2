<?php

namespace App\Data\Time;

use App\Enums\TimePresenceAttentionType;
use App\Models\WorkShift;

final class TimePresenceAttentionItem
{
    public function __construct(
        public TimePresenceAttentionType $type,
        public WorkShift $shift,
    ) {}
}
