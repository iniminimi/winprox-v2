<?php

namespace App\Console\Commands;

use App\Actions\Communication\ExportPendingAnnouncementTranslationsAction;
use App\Actions\Communication\ExportPendingDocumentTranslationsAction;
use App\Actions\Communication\ExportPendingIssueTranslationsAction;
use App\Actions\Communication\ExportPendingTaskTranslationsAction;
use App\Actions\Communication\ExportPendingUnitTranslationsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranslationExportCommand extends Command
{
    protected $signature = 'translation:export';

    protected $description = 'Export pending translations to storage/app/exports/translations.json';

    public function handle(
        ExportPendingIssueTranslationsAction $exportIssues,
        ExportPendingAnnouncementTranslationsAction $exportAnnouncements,
        ExportPendingUnitTranslationsAction $exportUnits,
        ExportPendingTaskTranslationsAction $exportTasks,
        ExportPendingDocumentTranslationsAction $exportDocuments,
    ): int {
        $items = array_merge(
            $exportIssues->handle()['items'],
            $exportAnnouncements->handle(),
            $exportUnits->handle(),
            $exportTasks->handle(),
            $exportDocuments->handle(),
        );

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'items' => $items,
        ];

        $path = storage_path('app/exports/translations.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $count = count($items);
        $this->info("Exported {$count} pending translation(s) to {$path}");

        return self::SUCCESS;
    }
}
