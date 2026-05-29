<?php

namespace App\Actions\Team;

use App\Models\Worker;

class DeleteWorkerAction
{
    public function handle(Worker $worker): void
    {
        $worker->devices()->delete();
        $worker->delete();
    }
}
