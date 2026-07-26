<?php

namespace App\Actions\Reservations;

use App\Models\Reservation;

class ExpirePendingReservationsAction
{
    public function handle(): int
    {
        // Overlap already ignores expired pending via blocking(); this only cleans timestamps for reporting.
        return Reservation::withoutGlobalScopes()
            ->whereNull('cancelled_at')
            ->whereNull('confirmed_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['cancelled_at' => now()]);
    }
}
