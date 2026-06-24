<?php

namespace App\Models;

use App\Enums\MunicipalPromoEmailSendStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalPromoEmailSend extends Model
{
    protected $fillable = [
        'campaign',
        'promo_recipient_id',
        'municipality_name',
        'recipient_email',
        'docx_filename',
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

    public function promoRecipient(): BelongsTo
    {
        return $this->belongsTo(PromoRecipient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
