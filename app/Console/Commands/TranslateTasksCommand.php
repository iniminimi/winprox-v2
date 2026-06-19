<?php

namespace App\Console\Commands;

use App\Actions\Communication\RunPendingTaskTranslationsAction;
use Illuminate\Console\Command;

class TranslateTasksCommand extends Command
{
    protected $signature = 'winprox:translate-tasks {--limit= : Max pending translations to process}';

    protected $description = 'Translate pending task descriptions via the configured translation provider (Ollama)';

    public function handle(RunPendingTaskTranslationsAction $run): int
    {
        $limit = $this->option('limit');
        $processed = $run->handle(
            $limit !== null ? (int) $limit : null,
        );

        $this->info("Processed {$processed} pending translation(s).");

        return self::SUCCESS;
    }
}
