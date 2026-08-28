<?php

namespace App\Models;

use App\Enums\HelpChatKnowledgeBaseEntryTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpChatKnowledgeBaseEntryTranslation extends Model
{
    protected $fillable = [
        'help_chat_knowledge_base_entry_id',
        'locale',
        'patterns',
        'answer',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'patterns' => 'array',
            'status' => HelpChatKnowledgeBaseEntryTranslationStatus::class,
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(HelpChatKnowledgeBaseEntry::class, 'help_chat_knowledge_base_entry_id');
    }
}
