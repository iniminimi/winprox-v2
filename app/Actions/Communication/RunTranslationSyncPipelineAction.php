<?php

namespace App\Actions\Communication;

use App\Contracts\TranslationSyncRemoteClient;
use App\Enums\TranslationSyncPhase;
use App\Support\Translation\TranslationSyncRemoteGateway;
use App\Support\Translation\TranslationSyncStatusStore;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

class RunTranslationSyncPipelineAction
{
    public function __construct(
        private TranslationSyncStatusStore $statusStore,
        private TranslationSyncRemoteClient $remote,
        private TranslateExportItemsAction $translateExportItems,
    ) {}

    public function handle(int $actorUserId): array
    {
        $this->remote->assertConfigured();

        if (! config('ollama.enabled', true)) {
            throw new InvalidArgumentException('translation_sync_ollama_disabled');
        }

        $workDir = (string) config('translation_sync.work_dir');
        $exportPath = $workDir.'/'.(string) config('translation_sync.export_filename');
        $importPath = $workDir.'/'.(string) config('translation_sync.import_filename');

        File::ensureDirectoryExists($workDir);

        try {
            $this->statusStore->write(TranslationSyncPhase::ExportingRemote, $actorUserId, [
                'started_at' => now()->toIso8601String(),
                'message' => null,
            ]);

            $this->remote->runExportOnRemote();

            $this->statusStore->write(TranslationSyncPhase::Downloading, $actorUserId);

            $this->remote->downloadExport($exportPath);

            $payload = json_decode(File::get($exportPath), true);
            if (! is_array($payload)) {
                throw new RuntimeException('translation_sync_invalid_export_json');
            }

            $items = $payload['items'] ?? [];
            if (! is_array($items)) {
                throw new RuntimeException('translation_sync_invalid_export_items');
            }

            $total = count($items);

            if ($total === 0) {
                $this->statusStore->write(TranslationSyncPhase::Completed, $actorUserId, [
                    'finished_at' => now()->toIso8601String(),
                    'total' => 0,
                    'imported' => 0,
                    'message' => 'nothing_pending',
                ]);

                return [
                    'total' => 0,
                    'imported' => 0,
                ];
            }

            $this->statusStore->write(TranslationSyncPhase::Translating, $actorUserId, [
                'total' => $total,
                'completed' => 0,
            ]);

            $translatedItems = $this->translateExportItems->handle(
                $items,
                function (int $completed, int $itemTotal, array $current) use ($actorUserId, $total): void {
                    $this->statusStore->write(TranslationSyncPhase::Translating, $actorUserId, [
                        'total' => $itemTotal,
                        'completed' => $completed,
                        'current_issue_id' => $current['issue_id'] ?? null,
                        'current_announcement_id' => $current['announcement_id'] ?? null,
                        'current_locale' => $current['locale'] ?? null,
                    ]);
                },
            );

            File::put($importPath, json_encode([
                'exported_at' => $payload['exported_at'] ?? now()->toIso8601String(),
                'items' => $translatedItems,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->statusStore->write(TranslationSyncPhase::Uploading, $actorUserId, [
                'total' => $total,
                'completed' => $total,
            ]);

            $this->remote->uploadImport($importPath);

            $this->statusStore->write(TranslationSyncPhase::ImportingRemote, $actorUserId, [
                'total' => $total,
                'completed' => $total,
            ]);

            $this->remote->runImportOnRemote();

            $imported = count($translatedItems);

            $this->statusStore->write(TranslationSyncPhase::Completed, $actorUserId, [
                'finished_at' => now()->toIso8601String(),
                'total' => $total,
                'imported' => $imported,
                'message' => null,
            ]);

            return [
                'total' => $total,
                'imported' => $imported,
            ];
        } catch (\Throwable $exception) {
            $this->statusStore->write(TranslationSyncPhase::Failed, $actorUserId, [
                'finished_at' => now()->toIso8601String(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
