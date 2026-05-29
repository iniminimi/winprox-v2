<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'unit_id',
        'title',
        'description',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'is_public',
        'requires_verification',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'is_public' => 'boolean',
        'requires_verification' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Publiek downloadbaar: openbaar én geen extra verificatie nodig.
     */
    public function isPubliclyDownloadable(): bool
    {
        return $this->is_public && ! $this->requires_verification;
    }
}
