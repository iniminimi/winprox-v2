<?php

namespace App\Data\Time;

use App\Enums\TimePresenceAttentionType;
use App\Models\WorkShift;

final class TimePresenceAttentionItem
{
    public function __construct(
        public TimePresenceAttentionType $type,
        public ?WorkShift $shift = null,
        public ?TimeRosterViewAttention $rosterView = null,
    ) {}

    public function listKey(): string
    {
        if ($this->rosterView !== null) {
            return 'roster-'.$this->rosterView->auditId;
        }

        return 'shift-'.(int) $this->shift?->id;
    }

    public function teamId(): ?int
    {
        if ($this->shift !== null) {
            return (int) $this->shift->internal_team_id;
        }

        return $this->rosterView?->teamId;
    }
}
