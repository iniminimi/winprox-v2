<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerLeaveEntitlement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'year',
        'days',
    ];

    protected $casts = [
        'year' => 'integer',
        'days' => 'decimal:1',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
