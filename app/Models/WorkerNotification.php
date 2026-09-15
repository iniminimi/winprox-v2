<?php

namespace App\Models;

use App\Enums\WorkerNotificationType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerNotification extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'type',
        'reference_id',
        'read_at',
    ];

    protected $casts = [
        'type' => WorkerNotificationType::class,
        'read_at' => 'datetime',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
