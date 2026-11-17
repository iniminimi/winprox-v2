<?php

namespace App\Console\Commands;

use App\Actions\Time\RemindForgottenClockOutsAction;
use Illuminate\Console\Command;

class RemindForgottenClockOutsCommand extends Command
{
    protected $signature = 'winprox:time-remind-clock-out';

    protected $description = 'Push a reminder when a worker is still clocked in after the planned shift';

    public function handle(RemindForgottenClockOutsAction $remind): int
    {
        $sent = $remind->handle();

        $this->info("Clock-out reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
