<?php

namespace App\Contracts;

interface TranslationSyncRemoteClient
{
    public function assertConfigured(): void;

    public function runExportOnRemote(): void;

    public function downloadExport(string $localPath): void;

    public function uploadImport(string $localPath): void;

    public function runImportOnRemote(): void;
}
