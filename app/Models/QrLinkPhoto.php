<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class QrLinkPhoto extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'qr_code_id',
        'unit_id',
        'path',
    ];

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function hasPublicFile(): bool
    {
        return is_string($this->path)
            && trim($this->path) !== ''
            && Storage::disk('public')->exists($this->path);
    }

    public function publicUrl(): ?string
    {
        if (! $this->hasPublicFile()) {
            return null;
        }

        return '/storage/'.str_replace('\\', '/', $this->path);
    }
}
