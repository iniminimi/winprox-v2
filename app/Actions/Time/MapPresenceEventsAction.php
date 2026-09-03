<?php

namespace App\Actions\Time;

use App\Enums\PresenceComplianceScope;
use App\Enums\PresenceSourceEvent;
use App\Enums\PresenceType;

class MapPresenceEventsAction
{
    public function handle(PresenceSourceEvent $source, PresenceComplianceScope $scope): PresenceType
    {
        if (! $scope->isAvailable()) {
            throw new \InvalidArgumentException('presence_scope_unavailable');
        }

        // Golf 1 schoonmaak (bekend): clock in / break end → IN; clock out / break start → OUT.
        // Golf 2 hergebruikt dezelfde mapping tot RSZ anders voorschrijft.
        return match ($source) {
            PresenceSourceEvent::ClockIn, PresenceSourceEvent::BreakEnd => PresenceType::In,
            PresenceSourceEvent::ClockOut, PresenceSourceEvent::BreakStart => PresenceType::Out,
        };
    }
}
