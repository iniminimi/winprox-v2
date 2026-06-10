<?php

namespace App\Actions\Portal;

use App\Models\Worker;

class ClearWorkerIconLockoutAction
{
    public function handle(Worker $worker): Worker
    {
        $worker->forceFill([
            'field_icon_failed_attempts' => 0,
            'field_icon_locked_at' => null,
        ])->save();

        return $worker->fresh();
    }
}
