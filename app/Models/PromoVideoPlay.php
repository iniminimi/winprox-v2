<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoVideoPlay extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promo_recipient_id',
        'video_key',
        'locale',
        'played_at',
    ];

    protected $casts = [
        'played_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(PromoRecipient::class, 'promo_recipient_id');
    }
}
