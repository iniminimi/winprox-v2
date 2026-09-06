<?php

namespace App\Actions\Portal;

use App\Actions\Time\ClearWorkerClockDeviceAction;
use App\Models\InternalTeam;
use App\Models\Worker;
use InvalidArgumentException;

class TeamleaderReleaseWorkerClockDeviceAction
{
    public function __construct(private ClearWorkerClockDeviceAction $clearDevice) {}

    public function handle(InternalTeam $team, Worker $actingTeamleader, Worker $target): Worker
    {
        if (! $actingTeamleader->is_teamleader || ! $actingTeamleader->is_active) {
            throw new InvalidArgumentException('not_teamleader');
        }

        if ((int) $actingTeamleader->internal_team_id !== (int) $team->id
            || (int) $target->internal_team_id !== (int) $team->id) {
            throw new InvalidArgumentException('wrong_team');
        }

        if ((int) $actingTeamleader->id === (int) $target->id) {
            throw new InvalidArgumentException('cannot_release_self');
        }

        return $this->clearDevice->handle($target);
    }
}
