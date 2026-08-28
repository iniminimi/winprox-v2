<?php

namespace App\Models;

use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpChatKnowledgeBaseEntry extends Model
{
    protected $fillable = [
        'original_language',
        'match_key',
        'patterns',
        'answer',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'patterns' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(HelpChatKnowledgeBaseEntryTranslation::class);
    }

    public function normalizedOriginalLanguage(): string
    {
        return LocaleSupport::normalize($this->original_language);
    }
}
