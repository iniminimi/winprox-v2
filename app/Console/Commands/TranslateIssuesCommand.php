<?php

namespace App\Console\Commands;

use App\Actions\Communication\RunPendingIssueTranslationsAction;
use Illuminate\Console\Command;

class TranslateIssuesCommand extends Command
{
    protected $signature = 'winprox:translate-issues {--limit= : Max pending translations to process}';

    protected $description = 'Translate pending issue descriptions via the configured translation provider (Ollama)';

    public function handle(RunPendingIssueTranslationsAction $run): int
    {
        $limit = $this->option('limit');
        $processed = $run->handle(
            $limit !== null ? (int) $limit : null,
        );

        $this->info("Processed {$processed} pending translation(s).");

        return self::SUCCESS;
    }
}
