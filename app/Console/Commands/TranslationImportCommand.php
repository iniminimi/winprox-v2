<?php

namespace App\Console\Commands;

use App\Actions\Communication\ImportAnnouncementTranslationsAction;
use App\Actions\Communication\ImportDocumentTranslationsAction;
use App\Actions\Communication\ImportIssueTranslationsAction;
use App\Actions\Communication\ImportTaskTranslationsAction;
use App\Actions\Communication\ImportUnitTranslationsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class TranslationImportCommand extends Command
{
    protected $signature = 'translation:import';

    protected $description = 'Import translated texts from storage/app/imports/translated.json';

    public function handle(
        ImportIssueTranslationsAction $importIssues,
        ImportAnnouncementTranslationsAction $importAnnouncements,
        ImportUnitTranslationsAction $importUnits,
        ImportTaskTranslationsAction $importTasks,
        ImportDocumentTranslationsAction $importDocuments,
    ): int {
        $path = storage_path('app/imports/translated.json');

        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            $this->error('Invalid JSON file.');

            return self::FAILURE;
        }

        $items = $decoded['items'] ?? $decoded;

        if (! is_array($items)) {
            $this->error('JSON must contain an items array.');

            return self::FAILURE;
        }

        $issueItems = [];
        $announcementItems = [];
        $unitItems = [];
        $taskItems = [];
        $documentItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['document_id'])) {
                $documentItems[] = $item;
            } elseif (isset($item['task_id'])) {
                $taskItems[] = $item;
            } elseif (isset($item['unit_id'])) {
                $unitItems[] = $item;
            } elseif (isset($item['announcement_id'])) {
                $announcementItems[] = $item;
            } else {
                $issueItems[] = $item;
            }
        }

        try {
            $count = $importIssues->handle($issueItems)
                + $importAnnouncements->handle($announcementItems)
                + $importUnits->handle($unitItems)
                + $importTasks->handle($taskItems)
                + $importDocuments->handle($documentItems);
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$count} translation(s).");

        return self::SUCCESS;
    }
}
