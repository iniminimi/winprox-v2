<?php

namespace App\Livewire\Concerns;

use App\Actions\Time\CountTimePresenceAttentionAction;
use App\Support\Tenancy;

trait ProvidesTimeNavAlarmCount
{
    protected function timeNavAlarmCount(): int
    {
        return app(CountTimePresenceAttentionAction::class)->handle((int) Tenancy::id());
    }
}
