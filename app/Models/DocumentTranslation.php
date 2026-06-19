<?php

namespace App\Models;

use App\Enums\DocumentTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTranslation extends Model
{
    protected $fillable = [
        'document_id',
        'locale',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => DocumentTranslationStatus::class,
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
