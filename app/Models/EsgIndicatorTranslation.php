<?php

namespace App\Models;

use App\Enums\EsgIndicatorTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EsgIndicatorTranslation extends Model
{
    protected $fillable = [
        'esg_indicator_id',
        'locale',
        'name',
        'options',
        'status',
    ];

    protected $casts = [
        'options' => 'array',
        'status' => EsgIndicatorTranslationStatus::class,
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(EsgIndicator::class, 'esg_indicator_id');
    }
}
