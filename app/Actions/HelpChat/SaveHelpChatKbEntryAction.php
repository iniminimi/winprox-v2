<?php

namespace App\Actions\HelpChat;

use App\Models\HelpChatKnowledgeBaseEntry;

class SaveHelpChatKbEntryAction
{
    public function __construct(
        private EnsureHelpChatKbEntryTranslationSlotsAction $ensureSlots,
        private InvalidateHelpChatKbEntryTranslationsOnSourceChangeAction $invalidateTranslations,
    ) {}

    /**
     * @param  list<string>  $patterns
     */
    public function handle(
        ?int $entryId,
        string $originalLanguage,
        string $matchKey,
        array $patterns,
        string $answer,
        bool $isActive,
    ): HelpChatKnowledgeBaseEntry {
        if ($patterns === []) {
            throw new \InvalidArgumentException('patterns_required');
        }

        $payload = [
            'original_language' => $originalLanguage,
            'match_key' => $matchKey,
            'patterns' => $patterns,
            'answer' => $answer,
            'is_active' => $isActive,
        ];

        if ($entryId !== null) {
            $entry = HelpChatKnowledgeBaseEntry::query()->findOrFail($entryId);
            $previousAnswer = (string) $entry->answer;
            $previousPatterns = array_values($entry->patterns ?? []);
            $entry->update($payload);
            $entry = $entry->fresh();

            if ($entry instanceof HelpChatKnowledgeBaseEntry) {
                $this->invalidateTranslations->handle($entry, $previousAnswer, $previousPatterns);
                $this->ensureSlots->handle($entry);
            }

            return $entry;
        }

        $entry = HelpChatKnowledgeBaseEntry::create($payload);
        $this->ensureSlots->handle($entry);

        return $entry;
    }
}
