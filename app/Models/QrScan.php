<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrScan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'qr_code_id',
        'tenant_id',
        'user_id',
        'ip_address',
        'user_agent',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (QrScan $scan) {
            if (empty($scan->scanned_at)) {
                $scan->scanned_at = now();
            }
        });
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
