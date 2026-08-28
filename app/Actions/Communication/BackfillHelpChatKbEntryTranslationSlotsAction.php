<?php

namespace App\Actions\Communication;

use App\Actions\HelpChat\EnsureHelpChatKbEntryTranslationSlotsAction;
use App\Models\HelpChatKnowledgeBaseEntry;

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

        return $count;
    }
}
