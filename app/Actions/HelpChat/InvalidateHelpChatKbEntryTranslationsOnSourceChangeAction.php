<?php

namespace App\Actions\HelpChat;

use App\Enums\HelpChatKnowledgeBaseEntryTranslationStatus;
use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatKnowledgeBaseEntryTranslation;
use App\Support\Translation\LocaleSupport;

class InvalidateHelpChatKbEntryTranslationsOnSourceChangeAction
{
    /**
     * @param  list<string>  $previousPatterns
     */
    public function handle(
        HelpChatKnowledgeBaseEntry $entry,
        string $previousAnswer,
        array $previousPatterns,
    ): void {
        $answerChanged = trim($previousAnswer) !== trim((string) $entry->answer);
        $patternsChanged = $previousPatterns !== array_values($entry->patterns ?? []);

        if (! $answerChanged && ! $patternsChanged) {
            return;
        }

        if (! $entry->is_active) {
            return;
        }

        $source = $entry->normalizedOriginalLanguage();

        HelpChatKnowledgeBaseEntryTranslation::query()
            ->where('help_chat_knowledge_base_entry_id', $entry->id)
            ->where('locale', '!=', $source)
            ->where(function ($query) {
                $query->where('status', '!=', HelpChatKnowledgeBaseEntryTranslationStatus::Pending->value)
                    ->orWhereNotNull('answer')
                    ->orWhereNotNull('patterns');
            })
            ->update([
                'patterns' => null,
                'answer' => null,
                'status' => HelpChatKnowledgeBaseEntryTranslationStatus::Pending->value,
            ]);
    }
}
