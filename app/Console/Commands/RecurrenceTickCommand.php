<?php

namespace App\Console\Commands;

use App\Actions\Tasks\CreateRecurringTaskCycleAction;
use App\Models\Issue;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Console\Command;

class RecurrenceTickCommand extends Command
{
    protected $signature = 'winprox:recurrence-tick';

    protected $description = 'Open due recurring issue cycles per tenant';

    public function handle(CreateRecurringTaskCycleAction $createCycle): int
    {
        $opened = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($createCycle, &$opened): void {
            Tenancy::actAs($tenant->id);

            Issue::query()
                ->where('is_recurring', true)
                ->where('recurrence_active', true)
                ->whereNull('recurrence_paused_at')
                ->whereNotNull('recurrence_next_due_at')
                ->each(function (Issue $issue) use ($createCycle, &$opened): void {
                    $task = $createCycle->handle($issue);
                    if ($task !== null) {
                        $opened++;
                    }
                });
        });

        Tenancy::forget();

        $this->info("Recurring cycles opened: {$opened}");

        return self::SUCCESS;
    }
}
