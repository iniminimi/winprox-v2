<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Worker;
use Illuminate\Support\Collection;

/**
 * Workers die het klokscherm in de lettertrie mag tonen: actief en
 * toegelaten op de locatie van dit Clock Point (zelfde regels als
 * Worker::canClockAt). `has_pin` bepaalt of het scherm een PIN vraagt
 * of er één laat instellen. Minimale payload — PIN-hashes verlaten
 * de server nooit.
 */
class ListClockDisplayWorkersAction
{
    /**
     * @return Collection<int, array{id: int, first_name: string, last_name: string, has_pin: bool, photo_url: ?string}>
     */
    public function handle(ClockPoint $clockPoint): Collection
    {
        $tenant = $clockPoint->tenant;
        $locationId = $clockPoint->location_id !== null ? (int) $clockPoint->location_id : null;

        $query = Worker::query()
            ->where('is_active', true);

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
            ->get(['id', 'first_name', 'last_name', 'clock_pin_hash', 'photo_path'])
            ->map(fn (Worker $w) => [
                'id' => (int) $w->id,
                'first_name' => (string) $w->first_name,
                'last_name' => (string) $w->last_name,
                'has_pin' => $w->hasClockPin(),
                'photo_url' => $w->photoPublicUrl(),
            ]);
    }
}
