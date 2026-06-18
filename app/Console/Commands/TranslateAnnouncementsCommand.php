<?php

namespace App\Console\Commands;

use App\Actions\Communication\RunPendingAnnouncementTranslationsAction;
use Illuminate\Console\Command;

class TranslateAnnouncementsCommand extends Command
{
    protected $signature = 'winprox:translate-announcements {--limit= : Max pending translations to process}';

    protected $description = 'Translate pending announcement descriptions via the configured translation provider (Ollama)';

    public function handle(RunPendingAnnouncementTranslationsAction $run): int
    {
        $limit = $this->option('limit');
        $processed = $run->handle(
            $limit !== null ? (int) $limit : null,
        );

        $this->info("Processed {$processed} pending translation(s).");

        return self::SUCCESS;
    }
}
