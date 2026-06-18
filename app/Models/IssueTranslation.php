<?php

namespace App\Models;

use App\Enums\IssueTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueTranslation extends Model
{
    protected $fillable = [
        'issue_id',
        'locale',
        'text',
        'status',
    ];

    protected $casts = [
        'status' => IssueTranslationStatus::class,
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
