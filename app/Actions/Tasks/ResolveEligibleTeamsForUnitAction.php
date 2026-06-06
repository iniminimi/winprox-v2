<?php

namespace App\Actions\Tasks;

use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Unit;
use Illuminate\Support\Collection;

readonly class ResolveEligibleTeamsForUnitAction
{
    /**
     * @return Collection<int, array{team: InternalTeam, is_primary: bool}>
     */
    public function handle(Unit $unit): Collection
    {
        if ($unit->category_id === null) {
            return new Collection();
        }

        $category = Category::find($unit->category_id);
        if ($category === null) {
            return new Collection();
        }

        $teams = $category->teams()->withPivot('is_primary')->get();

        return $teams->map(function (InternalTeam $team) {
            return [
                'team' => $team,
                'is_primary' => (bool) $team->pivot->is_primary,
            ];
        });
    }
}
