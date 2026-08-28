<?php

namespace App\Actions\Communication;

use App\Actions\HelpChat\EnsureHelpChatKbEntryTranslationSlotsAction;
use App\Enums\HelpChatKnowledgeBaseEntryTranslationStatus;
use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatKnowledgeBaseEntryTranslation;
use App\Support\Translation\LocaleSupport;
use App\Support\Translation\TranslationOutputGuard;
use Illuminate\Validation\ValidationException;

class ImportHelpChatKbEntryTranslationsAction
{
    public function __construct(
        private EnsureHelpChatKbEntryTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $entryId = (int) ($item['help_chat_kb_entry_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            $patterns = $item['patterns'] ?? null;

            if ($entryId <= 0 || $locale === '' || $answer === '') {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if ($patterns !== null && ! is_array($patterns)) {
                throw ValidationException::withMessages([
                    "items.{$index}.patterns" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            $entry = HelpChatKnowledgeBaseEntry::query()->find($entryId);

            if ($entry === null || ! $entry->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.help_chat_kb_entry_id" => [__('platform.help.kb_translation_import_missing')],
                ]);
            }

            if ($locale === $entry->normalizedOriginalLanguage()) {
                continue;
            }

            $sourceLocale = $entry->normalizedOriginalLanguage();
            if ($this->isRejectedKbTranslation($answer, (string) $entry->answer, $locale, $sourceLocale)) {
                continue;
            }

            $sourcePatterns = array_values($entry->patterns ?? []);
            $normalizedPatterns = null;

            if ($sourcePatterns !== []) {
                if (! is_array($patterns) || count($patterns) !== count($sourcePatterns)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.patterns" => [__('platform.help.kb_translation_import_patterns_mismatch')],
                    ]);
                }

                $normalizedPatterns = array_values(array_map(
                    static fn (mixed $pattern): string => trim((string) $pattern),
                    $patterns,
                ));

                foreach ($sourcePatterns as $index => $sourcePattern) {
                    $translatedPattern = $normalizedPatterns[$index] ?? '';
                    if ($this->isRejectedKbTranslation($translatedPattern, $sourcePattern, $locale, $sourceLocale)) {
                        continue 2;
                    }
                }
            }

            $this->ensureSlots->handle($entry);

            $row = HelpChatKnowledgeBaseEntryTranslation::query()
                ->where('help_chat_knowledge_base_entry_id', $entry->id)
                ->where('locale', $locale)
                ->firstOrFail();

            if (
                $row->status === HelpChatKnowledgeBaseEntryTranslationStatus::Completed
                && $row->answer === $answer
                && $row->patterns == $normalizedPatterns
            ) {
                continue;
            }

            $row->fill([
                'patterns' => $normalizedPatterns,
                'answer' => $answer,
                'status' => HelpChatKnowledgeBaseEntryTranslationStatus::Completed,
            ])->save();

            $imported++;
        }

        return $imported;
    }

    private function isRejectedKbTranslation(
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
