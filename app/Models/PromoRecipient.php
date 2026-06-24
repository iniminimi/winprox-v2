<?php

namespace App\Models;

use App\Enums\MunicipalPromoEmailSendStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function latestSentEmailSend(): HasOne
    {
        return $this->hasOne(MunicipalPromoEmailSend::class)
            ->ofMany(
                ['sent_at' => 'max'],
                fn ($query) => $query->where('status', MunicipalPromoEmailSendStatus::Sent),
            );
    }

    public function latestEmailSendAttempt(): HasOne
    {
        return $this->hasOne(MunicipalPromoEmailSend::class)->latestOfMany();
    }
}
