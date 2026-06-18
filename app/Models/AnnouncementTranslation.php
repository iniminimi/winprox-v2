<?php

namespace App\Models;

use App\Enums\AnnouncementTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementTranslation extends Model
{
    protected $fillable = [
        'announcement_id',
        'locale',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => AnnouncementTranslationStatus::class,
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
