<?php

declare(strict_types=1);

namespace App\Console\Commands\Mail;

use App\Actions\Marketing\PurgeBogusEmailUnsubscribesAction;
use Illuminate\Console\Command;

class PurgeBogusEmailUnsubscribesCommand extends Command
{
    protected $signature = 'marketing:purge-bogus-email-unsubscribes
                            {--dry-run : List bogus rows without deleting}';

    protected $description = 'Remove Message-ID / system-address rows wrongly added to email_unsubscribes';

    public function handle(PurgeBogusEmailUnsubscribesAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $action->handle($dryRun);

        $this->info($dryRun
            ? 'Dry-run: scanning email_unsubscribes for bogus addresses…'
            : 'Purging bogus email_unsubscribes…');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Scanned', (string) $result['scanned']],
                ['Purged', (string) $result['purged']],
            ],
        );

        if ($result['emails'] !== []) {
            $this->line('Addresses:');
            foreach ($result['emails'] as $email) {
                $this->line('  - '.$email);
            }
        }

        return self::SUCCESS;
    }
}
