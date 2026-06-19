<?php

namespace App\Console\Commands;

use App\Actions\Communication\RunPendingDocumentTranslationsAction;
use Illuminate\Console\Command;

class TranslateDocumentsCommand extends Command
{
    protected $signature = 'winprox:translate-documents {--limit= : Max pending translations to process}';

    protected $description = 'Translate pending document descriptions via the configured translation provider (Ollama)';

    public function handle(RunPendingDocumentTranslationsAction $run): int
    {
        $limit = $this->option('limit');
        $processed = $run->handle(
            $limit !== null ? (int) $limit : null,
        );

        $this->info("Processed {$processed} pending translation(s).");

        return self::SUCCESS;
    }
}
