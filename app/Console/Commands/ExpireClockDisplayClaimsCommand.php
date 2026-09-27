<?php

namespace App\Console\Commands;

use App\Actions\Time\ExpireClockDisplayClaimsAction;
use Illuminate\Console\Command;

class ExpireClockDisplayClaimsCommand extends Command
{
    protected $signature = 'winprox:time-expire-clock-display-claims';

    protected $description = 'Zet verlopen pending klokscherm-claims op de terminale status expired';

    public function handle(ExpireClockDisplayClaimsAction $expire): int
    {
        $count = $expire->handle();
        $this->info("Verlopen display-claims: {$count}");

        return self::SUCCESS;
    }
}
