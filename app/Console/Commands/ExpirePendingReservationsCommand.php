<?php

namespace App\Console\Commands;

use App\Actions\Reservations\ExpirePendingReservationsAction;
use Illuminate\Console\Command;

class ExpirePendingReservationsCommand extends Command
{
    protected $signature = 'winprox:reservations-expire-pending';

    protected $description = 'Mark expired pending reservation holds as cancelled';

    public function handle(ExpirePendingReservationsAction $expirePending): int
    {
        $count = $expirePending->handle();
        $this->info("Expired pending reservations: {$count}");

        return self::SUCCESS;
    }
}
