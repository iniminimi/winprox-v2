<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Marketing\SubmitIndexNowUrlsAction;
use Illuminate\Console\Command;
use Throwable;

class IndexNowSubmitCommand extends Command
{
    protected $signature = 'marketing:indexnow-submit
                            {urls?* : Optional absolute URLs (default: all marketing sitemap URLs)}
                            {--dry-run : Build payload without calling IndexNow}';

    protected $description = 'Submit marketing URLs to IndexNow (Bing and other search engines)';

    public function handle(SubmitIndexNowUrlsAction $submit): int
    {
        $urls = array_values(array_filter(
            array_map('strval', $this->argument('urls') ?? []),
            fn (string $url): bool => $url !== '',
        ));
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'Dry-run: preparing IndexNow submission…'
            : 'Submitting URLs to IndexNow…');

        try {
            $result = $submit->handle(urls: $urls, dryRun: $dryRun);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Host', $result['host']],
                ['Key location', $result['key_location']],
                ['URLs', (string) $result['submitted']],
                ['HTTP status', $result['status'] === null ? '—' : (string) $result['status']],
                ['Dry-run', $result['dry_run'] ? 'yes' : 'no'],
            ],
        );

        if ($result['error'] !== null) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info($dryRun
            ? 'Dry-run OK — no request sent.'
            : 'IndexNow accepted the submission.');

        return self::SUCCESS;
    }
}
