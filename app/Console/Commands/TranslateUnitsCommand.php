<?php

namespace App\Console\Commands;

use App\Actions\Communication\RunPendingUnitTranslationsAction;
use Illuminate\Console\Command;

class TranslateUnitsCommand extends Command
{
    protected $signature = 'winprox:translate-units {--limit= : Max pending translations to process}';

    protected $description = 'Translate pending unit names and descriptions via the configured translation provider (Ollama)';

    public function handle(RunPendingUnitTranslationsAction $run): int
    {
        $limit = $this->option('limit');
        $processed = $run->handle(
            $limit !== null ? (int) $limit : null,
        );

        $this->info("Processed {$processed} pending translation(s).");

        return self::SUCCESS;
    }
}
