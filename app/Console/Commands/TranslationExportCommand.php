<?php

namespace App\Console\Commands;

use App\Actions\Communication\ExportPendingIssueTranslationsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranslationExportCommand extends Command
{
    protected $signature = 'translation:export';

    protected $description = 'Export pending issue translations to storage/app/exports/translations.json';

    public function handle(ExportPendingIssueTranslationsAction $export): int
    {
        $payload = $export->handle();
        $path = storage_path('app/exports/translations.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $count = count($payload['items']);
        $this->info("Exported {$count} pending translation(s) to {$path}");

        return self::SUCCESS;
    }
}
