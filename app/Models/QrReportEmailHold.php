<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrReportEmailHold extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'description',
        'reporter_name',
        'reporter_contact',
        'original_language',
        'photo_paths',
        'token',
        'expires_at',
        'confirmed_at',
        'issue_id',
    ];

    protected $casts = [
        'photo_paths' => 'array',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * @return list<string>
     */
    public function storedPhotoPaths(): array
    {
        $paths = $this->photo_paths ?? [];
        if (! is_array($paths)) {
            return [];
        }

        $clean = [];
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                $clean[] = $path;
            }
        }

        return $clean;
    }
}
