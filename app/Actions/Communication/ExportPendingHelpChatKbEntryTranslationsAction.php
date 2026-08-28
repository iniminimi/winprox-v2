<?php

namespace App\Actions\Communication;

use App\Enums\HelpChatKnowledgeBaseEntryTranslationStatus;
use App\Models\HelpChatKnowledgeBaseEntryTranslation;

class ExportPendingHelpChatKbEntryTranslationsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        return HelpChatKnowledgeBaseEntryTranslation::query()
            ->where('status', HelpChatKnowledgeBaseEntryTranslationStatus::Pending)
            ->whereHas('entry', fn ($query) => $query->where('is_active', true))
            ->with('entry')
            ->orderBy('help_chat_knowledge_base_entry_id')
            ->orderBy('locale')
            ->get()
            ->map(function (HelpChatKnowledgeBaseEntryTranslation $row): array {
                $entry = $row->entry;

                return [
                    'help_chat_kb_entry_id' => $entry->id,
                    'source_locale' => $entry->normalizedOriginalLanguage(),
                    'source_answer' => (string) $entry->answer,
                    'source_patterns' => $entry->patterns ?? [],
                    'locale' => $row->locale,
                    'status' => HelpChatKnowledgeBaseEntryTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();
    }
}
