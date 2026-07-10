<?php

namespace App\Actions\Time;

use App\Models\ClockPointQrToken;

class FinalizeExpiredClockPointQrGraceTokensAction
{
    public function handle(): int
    {
        return ClockPointQrToken::query()
            ->whereNull('blocked_at')
            ->where('grace_ends_at', '<=', now())
            ->update(['blocked_at' => now()]);
    }
}
