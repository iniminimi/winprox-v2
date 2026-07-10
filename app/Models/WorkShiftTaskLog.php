<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkShiftTaskLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'work_shift_id',
        'task_id',
        'worker_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function durationMinutes(): int
    {
        $end = $this->ended_at ?? now();

        return max(0, (int) $this->started_at->diffInMinutes($end));
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }
}
