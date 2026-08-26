<?php

namespace App\Models;

use App\Enums\PromoLanding;
use App\Enums\PromoEmailsPauseReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCampaign extends Model
{
    protected $attributes = [
        'attach_letter_to_email' => false,
        'landing' => 'government',
    ];

    protected $fillable = [
        'slug',
        'name',
        'locale',
        'landing',
        'letter_body_html',
        'email_subject',
        'email_body_html',
        'attach_letter_to_email',
        'flow_image_path',
        'youtube_url',
        'emails_paused_at',
        'emails_paused_reason',
        'emails_paused_detail',
        'column_mapping',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'landing' => PromoLanding::class,
            'column_mapping' => 'array',
            'attach_letter_to_email' => 'boolean',
            'emails_paused_at' => 'datetime',
        ];
    }

    public function isEmailSendingPaused(): bool
    {
        return $this->emails_paused_at !== null;
    }

    public function emailsPauseReasonLabelKey(): ?string
    {
        $reason = PromoEmailsPauseReason::tryFromMixed($this->emails_paused_reason);

        return $reason?->labelKey();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(PromoCampaignImport::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PromoCampaignTarget::class);
    }

    public function emailSends(): HasMany
    {
        return $this->hasMany(PromoCampaignEmailSend::class);
    }

    public function lettersDirectory(): string
    {
        return storage_path('app/promo-campaigns/'.$this->slug.'/letters');
    }
}
