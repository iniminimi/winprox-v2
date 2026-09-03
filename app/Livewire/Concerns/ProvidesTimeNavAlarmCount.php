<?php

namespace App\Livewire\Concerns;

use App\Actions\Time\CountFailedPresenceSubmissionsAction;
use App\Actions\Time\CountTimePresenceAttentionAction;
use App\Support\Tenancy;

trait ProvidesTimeNavAlarmCount
{
    protected function timeNavAlarmCount(): int
    {
        return app(CountTimePresenceAttentionAction::class)->handle((int) Tenancy::id());
    }

    protected function timeNavCiaoFailCount(): int
    {
        return app(CountFailedPresenceSubmissionsAction::class)->handle((int) Tenancy::id());
    }
}
