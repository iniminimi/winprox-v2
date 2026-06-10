<?php

namespace App\Actions\Portal;

use App\Models\Worker;

class RecordWorkerIconFailedAttemptAction
{
    public function handle(Worker $worker, int $maxAttempts = 2): Worker
    {
        $failed = (int) $worker->field_icon_failed_attempts + 1;

        $worker->forceFill(['field_icon_failed_attempts' => $failed]);

        if ($failed >= $maxAttempts) {
            $worker->forceFill(['field_icon_locked_at' => now()]);
        }

        $worker->save();

        return $worker->fresh();
    }
}
