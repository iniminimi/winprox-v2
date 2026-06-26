<?php

namespace App\Actions\Communication;

use App\Services\Translation\TranslationProviderInterface;
use App\Support\Translation\LocaleSupport;
use App\Support\Translation\TranslationSyncCancelledException;

class TranslateExportItemsAction
{
    public function __construct(private TranslationProviderInterface $translator) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function handle(array $items, ?callable $onProgress = null, ?callable $shouldAbort = null): array
    {
        $total = count($items);
        $translated = [];

        foreach ($items as $index => $item) {
            if ($shouldAbort !== null && $shouldAbort()) {
                throw new TranslationSyncCancelledException('translation_sync_cancelled');
            }

            $issueId = (int) ($item['issue_id'] ?? 0);
            $announcementId = (int) ($item['announcement_id'] ?? 0);
            $locationId = (int) ($item['location_id'] ?? 0);
            $unitId = (int) ($item['unit_id'] ?? 0);
            $taskId = (int) ($item['task_id'] ?? 0);
            $documentId = (int) ($item['document_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $sourceText = trim((string) ($item['source_text'] ?? ''));
            $sourceName = trim((string) ($item['source_name'] ?? ''));

            if ($locationId > 0) {
                if ($locale === '' || $sourceName === '') {
                    continue;
                }

                $translatedName = trim($this->translator->translate($sourceName, $locale));
                $row = [
                    'locale' => $locale,
                    'location_id' => $locationId,
                    'name' => $translatedName !== '' ? $translatedName : $sourceName,
                ];

                $translated[] = $row;

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total, [
                        'location_id' => $locationId,
                        'locale' => $locale,
                    ]);
                }

                continue;
            }

            $sourceDescription = trim((string) ($item['source_description'] ?? ''));

            if ($unitId > 0) {
                if ($locale === '') {
                    continue;
                }

                $row = ['locale' => $locale, 'unit_id' => $unitId];

                if ($sourceName !== '') {
                    $translatedName = trim($this->translator->translate($sourceName, $locale));
                    $row['name'] = $translatedName !== '' ? $translatedName : $sourceName;
                }

                if ($sourceDescription !== '') {
                    $translatedDescription = trim($this->translator->translate($sourceDescription, $locale));
                    $row['description'] = $translatedDescription !== '' ? $translatedDescription : $sourceDescription;
                }

                if (! isset($row['name']) && ! isset($row['description'])) {
                    continue;
                }

                $translated[] = $row;

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total, [
                        'unit_id' => $unitId,
                        'locale' => $locale,
                    ]);
                }

                continue;
            }

            if ($taskId > 0) {
                if ($locale === '' || $sourceText === '') {
                    continue;
                }

                $text = trim($this->translator->translate($sourceText, $locale));

                if ($text === '') {
                    $text = $sourceText;
                }

                $translated[] = [
                    'task_id' => $taskId,
                    'locale' => $locale,
                    'description' => $text,
                ];

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total, [
                        'task_id' => $taskId,
                        'locale' => $locale,
                    ]);
                }

                continue;
            }

            if ($documentId > 0) {
                if ($locale === '' || $sourceText === '') {
                    continue;
                }

                $text = trim($this->translator->translate($sourceText, $locale));

                if ($text === '') {
                    $text = $sourceText;
                }

                $translated[] = [
                    'document_id' => $documentId,
                    'locale' => $locale,
                    'description' => $text,
                ];

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total, [
                        'document_id' => $documentId,
                        'locale' => $locale,
                    ]);
                }

                continue;
            }

            if (($issueId <= 0 && $announcementId <= 0) || $locale === '' || $sourceText === '') {
                continue;
            }

            $text = trim($this->translator->translate($sourceText, $locale));

            if ($text === '') {
                $text = $sourceText;
            }

            $row = ['locale' => $locale, 'description' => $text];

            if ($issueId > 0) {
                $row = ['issue_id' => $issueId] + $row;
            }

            if ($announcementId > 0) {
                $row = ['announcement_id' => $announcementId] + $row;
            }

            $translated[] = $row;

            if ($onProgress !== null) {
                $onProgress($index + 1, $total, [
                    'issue_id' => $issueId > 0 ? $issueId : null,
                    'announcement_id' => $announcementId > 0 ? $announcementId : null,
                    'locale' => $locale,
                ]);
            }
        }

        return $translated;
    }
}
