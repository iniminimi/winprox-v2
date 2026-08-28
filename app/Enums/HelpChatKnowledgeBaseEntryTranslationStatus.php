<?php

namespace App\Enums;

enum HelpChatKnowledgeBaseEntryTranslationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
