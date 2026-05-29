<?php

namespace App\Actions\Team;

use App\Models\Worker;

/**
 * Beheerder-"unlock": wist het persoonlijke icoon van de worker, reset de
 * lockout-teller/-tijd en verwijdert de gekoppelde veldtoestellen. Daarna moet
 * de worker zich op de werkvloer opnieuw identificeren en een icoon kiezen.
 */
class ResetWorkerIconAction
{
    public function handle(Worker $worker): Worker
    {
        $worker->devices()->delete();

        $worker->forceFill([
            'field_icon_slug' => null,
            'field_icon_failed_attempts' => 0,
            'field_icon_locked_at' => null,
        ])->save();

        return $worker;
    }
}
