<?php

namespace App\Models;

use App\Enums\PromoVisitPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoVisit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promo_recipient_id',
        'locale',
        'page',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
        'page' => PromoVisitPage::class,
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(PromoRecipient::class, 'promo_recipient_id');
    }
}
