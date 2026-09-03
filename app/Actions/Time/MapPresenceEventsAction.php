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

        // Schoonmaak (golf 1) én bouw (golf 2): zelfde IN/OUT tot RSZ bouwspecs anders eisen.
        return match ($scope) {
            PresenceComplianceScope::CiaoCleaning,
            PresenceComplianceScope::CiaoConstruction => match ($source) {
                PresenceSourceEvent::ClockIn, PresenceSourceEvent::BreakEnd => PresenceType::In,
                PresenceSourceEvent::ClockOut, PresenceSourceEvent::BreakStart => PresenceType::Out,
            },
        };
    }
}
