<?php

namespace App\Console\Commands;

use App\Actions\Communication\ImportIssueTranslationsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class TranslationImportCommand extends Command
{
    protected $signature = 'translation:import';

    protected $description = 'Import translated issue texts from storage/app/imports/translated.json';

    public function handle(ImportIssueTranslationsAction $import): int
    {
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

        try {
            $count = $import->handle($items);
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$count} translation(s).");

        return self::SUCCESS;
    }
}
