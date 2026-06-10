<?php

namespace App\Actions\HelpChat;

use App\Models\HelpChatKnowledgeBaseEntry;

class SaveHelpChatKbEntryAction
{
    /**
     * @param  list<string>  $patterns
     */
    public function handle(
        ?int $entryId,
        string $locale,
        string $matchKey,
        array $patterns,
        string $answer,
        bool $isActive,
    ): HelpChatKnowledgeBaseEntry {
        if ($patterns === []) {
            throw new \InvalidArgumentException('patterns_required');
        }

        $payload = [
            'locale' => $locale,
            'match_key' => $matchKey,
            'patterns' => $patterns,
            'answer' => $answer,
            'is_active' => $isActive,
        ];

        if ($entryId !== null) {
            $entry = HelpChatKnowledgeBaseEntry::query()->findOrFail($entryId);
            $entry->update($payload);

            return $entry->fresh();
        }

        return HelpChatKnowledgeBaseEntry::create($payload);
    }
}
