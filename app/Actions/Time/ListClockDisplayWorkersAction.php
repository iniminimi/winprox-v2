<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Worker;
use Illuminate\Support\Collection;

/**
 * Workers die het klokscherm in de lettertrie mag tonen: actief, met een
 * klok-PIN, en toegelaten op de locatie van dit Clock Point (zelfde regels
 * als Worker::canClockAt). Minimale payload — het scherm heeft enkel id +
 * naam nodig; PIN-hashes verlaten de server nooit.
 */
class ListClockDisplayWorkersAction
{
    /**
     * @return Collection<int, array{id: int, first_name: string, last_name: string}>
     */
    public function handle(ClockPoint $clockPoint): Collection
    {
        $tenant = $clockPoint->tenant;
        $locationId = $clockPoint->location_id !== null ? (int) $clockPoint->location_id : null;

        $query = Worker::query()
            ->where('is_active', true)
            ->whereNotNull('clock_pin_hash')
            ->where('clock_pin_hash', '!=', '');

        // Locatiescope zoals canClockAt: checkmate-tenants en punten zonder
        // locatie zijn tenant-breed; anders: pivot-gekoppeld aan deze locatie,
        // géén locatie gekoppeld (toegelaten overal), of team klokt overal.
        if ($locationId !== null && ! ($tenant !== null && $tenant->checkmateMode())) {
            $query->where(function ($q) use ($locationId) {
                $q->whereDoesntHave('locations')
                    ->orWhereHas('locations', fn ($l) => $l->where('locations.id', $locationId))
                    ->orWhereHas('team', fn ($t) => $t->where('clocks_all_locations', true));
            });
        }

        return $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Worker $w) => [
                'id' => (int) $w->id,
                'first_name' => (string) $w->first_name,
                'last_name' => (string) $w->last_name,
            ]);
    }
}
