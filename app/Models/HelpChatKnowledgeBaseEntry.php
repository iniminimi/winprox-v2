<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpChatKnowledgeBaseEntry extends Model
{
    protected $fillable = [
        'locale',
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
}
