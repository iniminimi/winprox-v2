<?php

namespace App\Models;

use App\Enums\QrCodeStatus;
use App\Models\Concerns\BelongsToTenant;
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
            if (empty($qrCode->sticker_number)) {
                $qrCode->sticker_number = self::generateStickerNumber();
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

    public static function generateStickerNumber(): string
    {
        // Human-readable format: QR-YYYY-XXXXX (e.g., QR-2026-00001)
        $year = date('Y');
        
        do {
            $random = str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            $stickerNumber = "QR-{$year}-{$random}";
        } while (static::withoutGlobalScopes()->where('sticker_number', $stickerNumber)->exists());

        return $stickerNumber;
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
