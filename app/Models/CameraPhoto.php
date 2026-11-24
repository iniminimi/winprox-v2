<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CameraPhoto extends Model
{
    use BelongsToTenant;

    public const DISK = 'local';

    protected $fillable = [
        'tenant_id',
        'camera_id',
        'path',
        'mime',
    ];

    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }
}
