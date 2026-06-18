<?php

namespace App\Actions\Communication;

use App\Services\Translation\TranslationProviderInterface;
use App\Support\Translation\LocaleSupport;

class TranslateExportItemsAction
{
    public function __construct(private TranslationProviderInterface $translator) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{issue_id: int, locale: string, description: string}>
     */
    public function handle(array $items, ?callable $onProgress = null): array
    {
        $total = count($items);
        $translated = [];

        foreach ($items as $index => $item) {
            $issueId = (int) ($item['issue_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $sourceText = trim((string) ($item['source_text'] ?? ''));

            if ($issueId <= 0 || $locale === '' || $sourceText === '') {
                continue;
            }

            $text = trim($this->translator->translate($sourceText, $locale));

            if ($text === '') {
                $text = $sourceText;
            }

            $translated[] = [
                'issue_id' => $issueId,
                'locale' => $locale,
                'description' => $text,
            ];

            if ($onProgress !== null) {
                $onProgress($index + 1, $total, [
                    'issue_id' => $issueId,
                    'locale' => $locale,
                ]);
            }
        }

        return $translated;
    }
}
