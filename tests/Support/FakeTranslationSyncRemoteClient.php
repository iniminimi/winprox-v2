<?php

namespace Tests\Support;

use App\Contracts\TranslationSyncRemoteClient;
use Illuminate\Support\Facades\File;

class FakeTranslationSyncRemoteClient implements TranslationSyncRemoteClient
{
    /** @var list<array<string, mixed>> */
    public array $exportItems = [];

    public int $exportRuns = 0;

    public int $importRuns = 0;

    public ?string $uploadedImportPath = null;

    public function assertConfigured(): void {}

    public function runExportOnRemote(): void
    {
        $this->exportRuns++;
    }

    public function downloadExport(string $localPath): void
    {
        File::ensureDirectoryExists(dirname($localPath));
        File::put($localPath, json_encode([
            'exported_at' => now()->toIso8601String(),
            'items' => $this->exportItems,
        ], JSON_THROW_ON_ERROR));
    }

    public function uploadImport(string $localPath): void
    {
        $this->uploadedImportPath = $localPath;
    }

    public function runImportOnRemote(): void
    {
        $this->importRuns++;
    }
}
