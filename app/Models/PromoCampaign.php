<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCampaign extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'locale',
        'letter_body_html',
        'email_subject',
        'email_body_html',
        'flow_image_path',
        'column_mapping',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
        ];
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
