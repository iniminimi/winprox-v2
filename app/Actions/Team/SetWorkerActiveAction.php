<?php

namespace App\Actions\Team;

use App\Models\Worker;

class SetWorkerActiveAction
{
    public function handle(Worker $worker, bool $active): Worker
    {
        $worker->update(['is_active' => $active]);

        return $worker;
    }
}
