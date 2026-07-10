<?php

namespace App\Console\Commands;

use App\Actions\Time\AutoCloseStaleWorkShiftsAction;
use Illuminate\Console\Command;

class AutoCloseStaleWorkShiftsCommand extends Command
{
    protected $signature = 'winprox:time-auto-close-stale {--hours= : Override stale threshold in hours}';

    protected $description = 'Close open work shifts that were left open too long';

    public function handle(AutoCloseStaleWorkShiftsAction $autoClose): int
    {
        $hours = $this->option('hours');
        $closed = $autoClose->handle($hours !== null ? (int) $hours : null);

        $this->info("Auto-closed stale work shifts: {$closed}");

        return self::SUCCESS;
    }
}
