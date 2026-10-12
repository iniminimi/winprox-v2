<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerWeekdayMinute extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'weekday',
        'minutes',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'minutes' => 'integer',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
