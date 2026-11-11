<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitAlbumSendStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén mail met foto's uit het album van een unit, naar één ontvanger.
 */
class UnitAlbumSend extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'user_id',
        'recipient_email',
        'photo_ids',
        'photo_count',
        'album_day',
        'status',
        'scheduled_at',
        'sent_at',
    ];

    protected $casts = [
        'photo_ids' => 'array',
        'photo_count' => 'integer',
        'album_day' => 'date',
        'status' => UnitAlbumSendStatus::class,
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
