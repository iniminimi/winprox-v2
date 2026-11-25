<?php

namespace App\Livewire\Concerns;

use App\Actions\Time\CountFailedPresenceSubmissionsAction;
use App\Actions\Time\CountTimePresenceAttentionAction;
use App\Models\User;
use App\Support\Tenancy;

trait ProvidesTimeNavAlarmCount
{
    protected function timeNavAlarmCount(): int
    {
        $viewer = auth()->user();

        return app(CountTimePresenceAttentionAction::class)->handle(
            (int) Tenancy::id(),
            $viewer instanceof User ? $viewer->accessibleLocationIds() : null,
        );
    }

    protected function timeNavCiaoFailCount(): int
    {
        return app(CountFailedPresenceSubmissionsAction::class)->handle((int) Tenancy::id());
    }
}
