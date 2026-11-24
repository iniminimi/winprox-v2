<?php

namespace App\Console\Commands;

use App\Actions\Sense\SyncSenseSeasonAction;
use Illuminate\Console\Command;

class SyncSenseSeasonCommand extends Command
{
    protected $signature = 'winprox:sense-sync-season';

    protected $description = 'Zet Sense-temperatuur en -vocht op de jaarhelft van vandaag';

    public function handle(SyncSenseSeasonAction $sync): int
    {
        $count = $sync->handleAll();
        $this->info("Sense-jaarhelft bijgewerkt: {$count}");

        return self::SUCCESS;
    }
}
