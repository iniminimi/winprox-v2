<?php

namespace App\Actions\Time;

use App\Enums\ClockDisplayClaimStatus;
use App\Models\ClockDisplayClaim;

/**
 * Scheduled cleanup: verlopen pending-claims krijgen de terminale status
 * `expired` (eerlijke weergave in de admin-lijst i.p.v. eeuwig "in afwachting").
 *
 * Bewust géén clock_point-lock: de predicate `status = pending` filtert rijen
 * die IssuePairingCode/ConfirmClaim/DenyClaim net terminal maakten automatisch
 * weg, en InnoDB row-locks serialiseren botsende writes. Slechtste geval: een
 * rij die eigenlijk denied(code_reissued) zou worden blijft `expired` —
 * terminale status, cosmetisch labelverschil, geen correctness-probleem.
 * Correctheid hangt sowieso op ClockDisplayClaim::isPending()/scopeActivePending.
 */
class ExpireClockDisplayClaimsAction
{
    public function handle(): int
    {
        return ClockDisplayClaim::query()
            ->where('status', ClockDisplayClaimStatus::Pending->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => ClockDisplayClaimStatus::Expired->value]);
    }
}
