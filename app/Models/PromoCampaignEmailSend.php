<?php

namespace App\Models;

use App\Enums\MunicipalPromoEmailSendStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCampaignEmailSend extends Model
{
    protected $fillable = [
        'promo_campaign_id',
        'promo_campaign_target_id',
        'recipient_email',
        'status',
        'error_message',
        'sent_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MunicipalPromoEmailSendStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromoCampaign::class, 'promo_campaign_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(PromoCampaignTarget::class, 'promo_campaign_target_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
