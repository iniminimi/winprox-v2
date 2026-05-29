<?php

namespace App\Actions\Team;

use App\Models\Worker;

/**
 * Wijst de teamleader-vlag toe of trekt hem in. Een teamleader mag (in het
 * veld-portaal — aparte follow-up) iconen van collega-workers vrijgeven.
 */
class SetWorkerTeamleaderAction
{
    public function handle(Worker $worker, bool $isTeamleader): Worker
    {
        $worker->update(['is_teamleader' => $isTeamleader]);

        return $worker;
    }
}
