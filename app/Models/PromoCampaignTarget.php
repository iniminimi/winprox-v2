<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PromoCampaignTarget extends Model
{
    protected $fillable = [
        'promo_campaign_id',
        'promo_campaign_import_id',
        'promo_recipient_id',
        'name',
        'email',
        'street_address',
        'postal_code',
        'city',
        'notes',
        'docx_filename',
        'generated_at',
        'undelivered',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'undelivered' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromoCampaign::class, 'promo_campaign_id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PromoCampaignImport::class, 'promo_campaign_import_id');
    }

    public function promoRecipient(): BelongsTo
    {
        return $this->belongsTo(PromoRecipient::class);
    }

    public function latestEmailSend(): HasOne
    {
        return $this->hasOne(PromoCampaignEmailSend::class)->latestOfMany();
    }

    public function latestSentEmailSend(): HasOne
    {
        return $this->hasOne(PromoCampaignEmailSend::class)
            ->ofMany(
                ['sent_at' => 'max'],
                fn ($query) => $query->where('status', \App\Enums\MunicipalPromoEmailSendStatus::Sent),
            );
    }

    public function slug(): string
    {
        $nameSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($this->name)) ?: 'target';
        $nameSlug = str_replace('-', '_', trim($nameSlug, '-'));
        $postalCode = preg_replace('/\D/', '', (string) $this->postal_code);
        if ($postalCode === '') {
            return $nameSlug;
        }

        return $postalCode.'_'.$nameSlug;
    }
}
