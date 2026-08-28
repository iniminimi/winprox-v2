<?php

namespace App\Actions\Communication;

use App\Services\Translation\TranslationProviderInterface;
use App\Support\Translation\LocaleSupport;
use App\Support\Translation\TranslationSyncCancelledException;

class TranslateExportItemsAction
{
    private const SHORT_NAME_MAX = 255;

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
            $esgIndicatorId = (int) ($item['esg_indicator_id'] ?? 0);
            $unitCheckListId = (int) ($item['unit_check_list_id'] ?? 0);
            $helpChatKbEntryId = (int) ($item['help_chat_kb_entry_id'] ?? 0);
            $categoryId = (int) ($item['category_id'] ?? 0);
            $internalTeamId = (int) ($item['internal_team_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $sourceText = trim((string) ($item['source_text'] ?? ''));
            $sourceName = trim((string) ($item['source_name'] ?? ''));

            if ($helpChatKbEntryId > 0) {
                $sourceAnswer = trim((string) ($item['source_answer'] ?? ''));

                if ($locale === '' || $sourceAnswer === '') {
                    continue;
                }

                $text = trim($this->translator->translate($sourceAnswer, $locale));
                if ($text === '') {
                    $text = $sourceAnswer;
                }

                $row = [
                    'locale' => $locale,
                    'help_chat_kb_entry_id' => $helpChatKbEntryId,
                    'answer' => $text,
                ];

                $sourcePatterns = $item['source_patterns'] ?? [];
                if (is_array($sourcePatterns) && $sourcePatterns !== []) {
                    $translatedPatterns = [];
                    foreach ($sourcePatterns as $pattern) {
                        $translatedPatterns[] = $this->translateShortName((string) $pattern, $locale);
                    }
                    $row['patterns'] = $translatedPatterns;
                }

                $translated[] = $row;

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total, [
                        'help_chat_kb_entry_id' => $helpChatKbEntryId,
                        'locale' => $locale,
                    ]);
                }

                continue;
            }

            if ($locationId > 0) {
                if ($locale === '' || $sourceName === '') {
                    continue;
                }

                $translatedName = $this->translateShortName($sourceName, $locale);
                $row = [
                    'locale' => $locale,
                    'location_id' => $locationId,
                    'name' => $translatedName,
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

            if ($esgIndicatorId > 0) {
                if ($locale === '' || $sourceName === '') {
                    continue;
                }

                $translatedName = $this->translateShortName($sourceName, $locale);
                $row = [
                    'locale' => $locale,
                    'esg_indicator_id' => $esgIndicatorId,
                    'name' => $translatedName,
                ];

                $sourceOptions = $item['source_options'] ?? [];
                if (is_array($sourceOptions) && $sourceOptions !== []) {
                    $translatedOptions = [];
                    foreach ($sourceOptions as $option) {
                        $translatedOption = trim($this->translator->translate((string) $option, $locale));
                        $translatedOptions[] = $translatedOption !== '' ? $translatedOption : (string) $option;
                    }
                    $row['options'] = $translatedOptions;
                }

                $translated[] = $row;

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total, [
                        'esg_indicator_id' => $esgIndicatorId,
                        'locale' => $locale,
                    ]);
                }

                continue;
            }

            if ($unitCheckListId > 0) {
                if ($locale === '' || $sourceName === '') {
                    continue;
                }

                $row = [
                    'locale' => $locale,
                    'unit_check_list_id' => $unitCheckListId,
                    'name' => $this->translateShortName($sourceName, $locale),
                ];

                $sourceItems = $item['source_items'] ?? [];
                if (is_array($sourceItems) && $sourceItems !== []) {
                    $translatedItems = [];
                    foreach ($sourceItems as $label) {
                        $translatedItems[] = $this->translateShortName((string) $label, $locale);
                    }
                    $row['items'] = $translatedItems;
                }

                $translated[] = $row;

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total, [
                        'unit_check_list_id' => $unitCheckListId,
                        'locale' => $locale,
                    ]);
                }

                continue;
            }

            if ($categoryId > 0) {
                if ($locale === '' || $sourceName === '') {
                    continue;
                }

                $translatedName = $this->translateShortName($sourceName, $locale);

                $translated[] = [
                    'category_id' => $categoryId,
                    'locale' => $locale,
                    'name' => $translatedName,
                ];

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total, [
                        'category_id' => $categoryId,
                        'locale' => $locale,
                    ]);
                }

                continue;
            }

            if ($internalTeamId > 0) {
                if ($locale === '' || $sourceName === '') {
                    continue;
                }

                $translatedName = $this->translateShortName($sourceName, $locale);

                $translated[] = [
                    'internal_team_id' => $internalTeamId,
                    'locale' => $locale,
                    'name' => $translatedName,
                ];

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total, [
                        'internal_team_id' => $internalTeamId,
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
                    $row['name'] = $this->translateShortName($sourceName, $locale);
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

    private function translateShortName(string $sourceName, string $locale): string
    {
        $translatedName = trim($this->translator->translate($sourceName, $locale));

        if ($translatedName === '') {
            $translatedName = $sourceName;
        }

        $translatedName = preg_replace('/\s+/u', ' ', $translatedName) ?? $translatedName;
        $translatedName = trim($translatedName);

        // Short labels must stay short — truncate runaway provider output.
        // Tests expect always a maximum label size (not falling back to source).
        if (mb_strlen($translatedName) > self::SHORT_NAME_MAX) {
            $translatedName = mb_substr($translatedName, 0, self::SHORT_NAME_MAX);
            $translatedName = trim($translatedName);
        }

        return $translatedName;
    }
}
