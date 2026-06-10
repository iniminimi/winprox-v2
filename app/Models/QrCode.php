<?php

namespace App\Models;

use App\Enums\QrCodeStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Qr\QrStickerNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\UUID;

class QrCode extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'token',
        'sticker_number',
        'unit_id',
        'status',
        'linked_at',
        'linked_by',
        'last_scanned_at',
        'notes',
    ];

    protected $casts = [
        'status' => QrCodeStatus::class,
        'linked_at' => 'datetime',
        'last_scanned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (QrCode $qrCode) {
            if (empty($qrCode->uuid)) {
                $qrCode->uuid = (string) Str::uuid();
            }
            if (empty($qrCode->token)) {
                $qrCode->token = self::generateUniqueToken();
            }
        });
    }

    public static function generateUniqueToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (static::withoutGlobalScopes()->where('token', $token)->exists());

        return $token;
    }

    /** @return Attribute<string, never> */
    protected function displayStickerNumber(): Attribute
    {
        return Attribute::get(fn (): string => QrStickerNumber::display($this->sticker_number));
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(QrScan::class);
    }

    public function qrLinkPhotos(): HasMany
    {
        return $this->hasMany(QrLinkPhoto::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', QrCodeStatus::Active);
    }

    public function scopeUnassigned($query)
    {
        return $query->where('status', QrCodeStatus::Unassigned);
    }

    public function isLinked(): bool
    {
        return $this->unit_id !== null;
    }

    public function canBeLinked(): bool
    {
        return $this->status->canLink() && !$this->isLinked();
    }

    public function canBeScanned(): bool
    {
        return $this->status->canScan() && $this->isLinked();
    }
}
