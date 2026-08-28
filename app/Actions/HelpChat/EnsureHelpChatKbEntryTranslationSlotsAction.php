<?php

namespace App\Actions\HelpChat;

use App\Enums\HelpChatKnowledgeBaseEntryTranslationStatus;
use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatKnowledgeBaseEntryTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureHelpChatKbEntryTranslationSlotsAction
{
    public function handle(HelpChatKnowledgeBaseEntry $entry): void
    {
        if (! $entry->is_active) {
            return;
        }

        foreach (LocaleSupport::targetLocalesForSource($entry->original_language) as $locale) {
            $row = HelpChatKnowledgeBaseEntryTranslation::firstOrCreate(
                [
                    'help_chat_knowledge_base_entry_id' => $entry->id,
                    'locale' => $locale,
                ],
                [
                    'status' => HelpChatKnowledgeBaseEntryTranslationStatus::Pending,
                ],
            );

            if (
                $row->status === HelpChatKnowledgeBaseEntryTranslationStatus::Failed
                && blank($row->answer)
            ) {
                $row->fill([
                    'status' => HelpChatKnowledgeBaseEntryTranslationStatus::Pending,
                ])->save();
            }
        }
    }
}
