<?php

namespace App\Actions\Communication;

use App\Actions\HelpChat\EnsureHelpChatKbEntryTranslationSlotsAction;
use App\Enums\HelpChatKnowledgeBaseEntryTranslationStatus;
use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatKnowledgeBaseEntryTranslation;
use App\Support\Translation\TranslationOutputGuard;

class BackfillHelpChatKbEntryTranslationSlotsAction
{
    public function __construct(
        private EnsureHelpChatKbEntryTranslationSlotsAction $ensureSlots,
    ) {}

    public function handle(): int
    {
        $count = 0;

        HelpChatKnowledgeBaseEntry::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(100, function ($entries) use (&$count): void {
                foreach ($entries as $entry) {
                    $this->ensureSlots->handle($entry);
                    $count++;
                }
            });

        HelpChatKnowledgeBaseEntryTranslation::query()
            ->where('status', HelpChatKnowledgeBaseEntryTranslationStatus::Completed)
            ->with('entry')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $entry = $row->entry;
                    if ($entry === null || blank($row->answer)) {
                        continue;
                    }

                    $sourceLocale = $entry->normalizedOriginalLanguage();
                    $rejectAnswer = TranslationOutputGuard::isUnusable((string) $row->answer, (string) $entry->answer)
                        || TranslationOutputGuard::isUntranslatedEcho(
                            (string) $row->answer,
                            (string) $entry->answer,
                            (string) $row->locale,
                            $sourceLocale,
                        );

                    $rejectPatterns = false;
                    $sourcePatterns = array_values($entry->patterns ?? []);
                    $translatedPatterns = array_values($row->patterns ?? []);
                    if ($sourcePatterns !== [] && count($sourcePatterns) === count($translatedPatterns)) {
                        foreach ($sourcePatterns as $index => $sourcePattern) {
                            $translatedPattern = $translatedPatterns[$index] ?? '';
                            if ($this->isRejectedPattern($translatedPattern, $sourcePattern, (string) $row->locale, $sourceLocale)) {
                                $rejectPatterns = true;
                                break;
                            }
                        }
                    }

                    if (! $rejectAnswer && ! $rejectPatterns) {
                        continue;
                    }

                    $row->fill([
                        'patterns' => null,
                        'answer' => null,
                        'status' => HelpChatKnowledgeBaseEntryTranslationStatus::Pending,
                    ])->save();
                }
            });

        return $count;
    }

    private function isRejectedPattern(
        string $text,
        string $source,
        string $targetLocale,
        string $sourceLocale,
    ): bool {
        if ($text === '') {
            return true;
        }

        return TranslationOutputGuard::isUnusable($text, $source)
            || TranslationOutputGuard::isUntranslatedEcho($text, $source, $targetLocale, $sourceLocale);
    }
}
