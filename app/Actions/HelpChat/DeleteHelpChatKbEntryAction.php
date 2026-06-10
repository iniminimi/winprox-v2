<?php

namespace App\Actions\HelpChat;

use App\Models\HelpChatKnowledgeBaseEntry;

class DeleteHelpChatKbEntryAction
{
    public function handle(int $entryId): void
    {
        HelpChatKnowledgeBaseEntry::query()->whereKey($entryId)->delete();
    }
}
