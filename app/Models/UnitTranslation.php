<?php

namespace App\Models;

use App\Enums\UnitTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitTranslation extends Model
{
    protected $fillable = [
        'unit_id',
        'locale',
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => UnitTranslationStatus::class,
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
