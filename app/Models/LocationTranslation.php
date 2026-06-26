<?php

namespace App\Models;

use App\Enums\LocationTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationTranslation extends Model
{
    protected $fillable = [
        'location_id',
        'locale',
        'name',
        'status',
    ];

    protected $casts = [
        'status' => LocationTranslationStatus::class,
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
