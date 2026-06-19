<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoRecipient extends Model
{
    protected $fillable = [
        'token',
        'label',
        'note',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(PromoVisit::class);
    }

    public function videoPlays(): HasMany
    {
        return $this->hasMany(PromoVideoPlay::class);
    }
}
