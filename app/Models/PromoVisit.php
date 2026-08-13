<?php

namespace App\Models;

use App\Enums\PromoVisitFollowKey;
use App\Enums\PromoVisitKind;
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
        'kind',
        'follow_key',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
        'page' => PromoVisitPage::class,
        'kind' => PromoVisitKind::class,
        'follow_key' => PromoVisitFollowKey::class,
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(PromoRecipient::class, 'promo_recipient_id');
    }
}
