<?php

namespace App\Actions\Communication;

use App\Services\Translation\TranslationProviderInterface;
use App\Support\Translation\LocaleSupport;

class TranslateExportItemsAction
{
    public function __construct(private TranslationProviderInterface $translator) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function handle(array $items, ?callable $onProgress = null): array
    {
        $total = count($items);
        $translated = [];

        foreach ($items as $index => $item) {
            $issueId = (int) ($item['issue_id'] ?? 0);
            $announcementId = (int) ($item['announcement_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $sourceText = trim((string) ($item['source_text'] ?? ''));

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
