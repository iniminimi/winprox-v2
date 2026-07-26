<?php

namespace App\Actions\Portal;

use App\Support\Portal\WorkerTaskBaseline;

class ClearWorkerTaskBaselineAction
{
    public function handle(int $teamId): void
    {
        WorkerTaskBaseline::clearForTeam($teamId);
    }
}
