<?php

declare(strict_types=1);

namespace App\Console\Commands\Mail;

use App\Actions\Marketing\ProcessPromoMailboxBouncesAction;
use Illuminate\Console\Command;
use Throwable;

class ProcessPromoBouncesCommand extends Command
{
    protected $signature = 'marketing:process-promo-bounces
                            {--all : Also process already-read bounce messages}
                            {--limit= : Max messages to scan}
                            {--dry-run : Parse and report without updating DB or marking Seen}';

    protected $description = 'Scan Dominique promo IMAP mailbox for bounce DSNs and exclude recipients from future promo sends';

    public function handle(ProcessPromoMailboxBouncesAction $process): int
    {
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null && $limitOption !== '' ? (int) $limitOption : null;
        $unseenOnly = ! (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'Dry-run: scanning promo mailbox for bounces…'
            : 'Scanning promo mailbox for bounces…');

        try {
            $result = $process->handle(
                unseenOnly: $unseenOnly,
                limit: $limit,
                dryRun: $dryRun,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Messages scanned', (string) $result['scanned']],
                ['Bounce messages', (string) $result['bounce_messages']],
                ['Emails found', (string) $result['emails_found']],
                ['Sends marked bounced', (string) $result['marked']],
                ['Addresses blocked', (string) $result['blocked']],
                ['Dry-run', $result['dry_run'] ? 'yes' : 'no'],
            ],
        );

        return self::SUCCESS;
    }
}
