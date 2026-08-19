<?php

namespace App\Actions\Communication;

use App\Contracts\TranslationSyncRemoteClient;
use App\Enums\TranslationSyncPhase;
use App\Support\Translation\TranslationSyncRemoteGateway;
use App\Support\Translation\TranslationSyncCancelledException;
use App\Support\Translation\TranslationSyncCancellation;
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

        $total = 0;
        $importedTotal = 0;

        try {
            $this->assertNotCancelled();

            $this->statusStore->write(TranslationSyncPhase::ExportingRemote, $actorUserId, [
                'started_at' => now()->toIso8601String(),
                'message' => null,
            ]);

            $this->remote->runExportOnRemote();

            $this->assertNotCancelled();

            $this->statusStore->write(TranslationSyncPhase::Downloading, $actorUserId);

            $this->remote->downloadExport($exportPath);

            $this->assertNotCancelled();

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

            $this->writeProgress(TranslationSyncPhase::Translating, $actorUserId, $total, 0, 0);

            $exportedAt = is_string($payload['exported_at'] ?? null) && $payload['exported_at'] !== ''
                ? (string) $payload['exported_at']
                : now()->toIso8601String();

            $batchSize = max(1, (int) config('translation_sync.batch_size', 50));
            $processed = 0;
            $translatedTotal = 0;

            // Per reeks vertalen, uploaden en importeren: valt de run halverwege uit, dan blijft
            // het al geïmporteerde werk op de server staan en pakt een nieuwe run de rest op.
            foreach (array_chunk($items, $batchSize) as $batch) {
                $this->assertNotCancelled();

                $offset = $processed;

                $translatedItems = $this->translateExportItems->handle(
                    $batch,
                    function (int $completed, int $batchTotal, array $current) use ($actorUserId, $total, $offset, &$importedTotal): void {
                        $this->assertNotCancelled();

                        $this->writeProgress(
                            TranslationSyncPhase::Translating,
                            $actorUserId,
                            $total,
                            $offset + $completed,
                            $importedTotal,
                            $current,
                        );
                    },
                    fn (): bool => TranslationSyncCancellation::requested(),
                );

                $processed += count($batch);
                $translatedTotal += count($translatedItems);

                // Niets vertaalbaars in deze reeks (bv. lege brontekst): niets om te importeren.
                if ($translatedItems === []) {
                    continue;
                }

                $this->assertNotCancelled();

                File::put($importPath, json_encode([
                    'exported_at' => $exportedAt,
                    'items' => $translatedItems,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $this->writeProgress(TranslationSyncPhase::Uploading, $actorUserId, $total, $processed, $importedTotal);

                $this->remote->uploadImport($importPath);

                $this->assertNotCancelled();

                $this->writeProgress(TranslationSyncPhase::ImportingRemote, $actorUserId, $total, $processed, $importedTotal);

                $imported = $this->remote->runImportOnRemote();

                if ($imported === 0) {
                    throw new RuntimeException(__('platform.translation_sync.error_remote_import_zero'));
                }

                if ($imported < count($translatedItems)) {
                    throw new RuntimeException(__('platform.translation_sync.error_remote_import_partial', [
                        'imported' => $imported,
                        'total' => count($translatedItems),
                    ]));
                }

                $importedTotal += $imported;
            }

            if ($translatedTotal === 0) {
                throw new RuntimeException(__('platform.translation_sync.error_nothing_translated'));
            }

            $this->statusStore->write(TranslationSyncPhase::Completed, $actorUserId, [
                'finished_at' => now()->toIso8601String(),
                'total' => $total,
                'imported' => $importedTotal,
                'message' => $translatedTotal < $total
                    ? __('platform.translation_sync.error_partial_translated', [
                        'translated' => $translatedTotal,
                        'total' => $total,
                    ])
                    : null,
            ]);

            return [
                'total' => $total,
                'imported' => $importedTotal,
            ];
        } catch (TranslationSyncCancelledException) {
            $this->statusStore->write(TranslationSyncPhase::Cancelled, $actorUserId, [
                'finished_at' => now()->toIso8601String(),
                'total' => $total,
                'imported' => $importedTotal,
                'message' => 'cancelled',
            ]);
            TranslationSyncCancellation::clear();

            return [
                'total' => $total,
                'imported' => $importedTotal,
                'cancelled' => true,
            ];
        } catch (\Throwable $exception) {
            $this->statusStore->write(TranslationSyncPhase::Failed, $actorUserId, [
                'finished_at' => now()->toIso8601String(),
                'total' => $total,
                'imported' => $importedTotal,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $current
     */
    private function writeProgress(
        TranslationSyncPhase $phase,
        int $actorUserId,
        int $total,
        int $completed,
        int $imported,
        array $current = [],
    ): void {
        $this->statusStore->write($phase, $actorUserId, [
            'total' => $total,
            'completed' => $completed,
            'imported' => $imported,
            'current_issue_id' => $current['issue_id'] ?? null,
            'current_announcement_id' => $current['announcement_id'] ?? null,
            'current_location_id' => $current['location_id'] ?? null,
            'current_unit_id' => $current['unit_id'] ?? null,
            'current_task_id' => $current['task_id'] ?? null,
            'current_document_id' => $current['document_id'] ?? null,
            'current_locale' => $current['locale'] ?? null,
        ]);
    }

    private function assertNotCancelled(): void
    {
        if (TranslationSyncCancellation::requested()) {
            throw new TranslationSyncCancelledException('translation_sync_cancelled');
        }
    }
}
